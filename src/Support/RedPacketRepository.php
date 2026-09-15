<?php

namespace Doingfb\RedPacket\Support;

use Doingfb\RedPacket\Model\RedPacket;
use Doingfb\RedPacket\Model\RedPacketClaim;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Ramon\PointSystem\Repository\PointsRepository;

class RedPacketRepository
{
    public function __construct(
        private ConnectionInterface $db,
        private PointsRepository $points,
        private RedPacketSettings $settings
    ) {
    }

    public function findOrFail(int $id): RedPacket
    {
        return RedPacket::query()
            ->with(['user', 'claims'])
            ->findOrFail($id);
    }

    public function create(
        User $actor,
        int $totalAmount,
        int $totalCount,
        string $distribution,
        string $greeting
    ): RedPacket {
        $actor->assertRegistered();
        $this->assertCanCreate($actor);

        $totalAmount = max(0, $totalAmount);
        $totalCount = max(1, $totalCount);
        $distribution = in_array($distribution, ['average', 'random'], true)
            ? $distribution
            : 'average';
        $greeting = trim(mb_substr($greeting, 0, 120));

        $this->validateAmount($totalAmount, $totalCount, $distribution);

        $randomAmounts = $distribution === 'random'
            ? $this->buildRandomClaimAmounts($totalAmount, $totalCount)
            : null;

        $packet = $this->db->transaction(function () use (
            $actor,
            $totalAmount,
            $totalCount,
            $distribution,
            $greeting,
            $randomAmounts
        ): RedPacket {
            $packet = new RedPacket();
            $packet->user_id = (int) $actor->id;
            $packet->total_amount = $totalAmount;
            $packet->total_count = $totalCount;
            $packet->claimed_amount = 0;
            $packet->claimed_count = 0;
            $packet->distribution = $distribution;
            $packet->random_amounts = $randomAmounts;
            $packet->greeting = $greeting !== '' ? $greeting : '恭喜发财，祝你好运！';
            $packet->expires_at = Carbon::now()->addMinutes($this->settings->expiresMinutes());
            $packet->published_at = null;
            $packet->save();

            try {
                $this->points->deduct(
                    $actor,
                    $totalAmount,
                    'red_packet.create',
                    'doingfb-red-packet',
                    (int) $packet->id
                );
            } catch (\DomainException $exception) {
                if ($exception->getMessage() === 'Insufficient point balance') {
                    throw new ValidationException([
                        'totalAmount' => '积分余额不足，无法发放红包。',
                    ]);
                }

                throw $exception;
            }

            return $packet;
        });

        return $this->findOrFail((int) $packet->id);
    }

    public function claim(User $actor, int $packetId): RedPacket
    {
        $actor->assertRegistered();
        $this->assertCanClaim($actor);

        $expired = false;

        $packet = $this->db->transaction(function () use ($actor, $packetId, &$expired): RedPacket {
            /** @var RedPacket $packet */
            $packet = RedPacket::query()
                ->whereKey($packetId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($packet->refunded_at !== null) {
                throw new ValidationException(['redPacket' => '红包已退款。']);
            }

            if ($packet->published_at === null) {
                throw new ValidationException(['redPacket' => '红包尚未发布。']);
            }

            if ($packet->isExpired()) {
                $this->refundLocked($packet);
                $expired = true;

                return $packet;
            }

            if ($packet->isFullyClaimed()) {
                throw new ValidationException(['redPacket' => '红包已经被领完。']);
            }

            if (
                RedPacketClaim::query()
                    ->where('red_packet_id', $packet->id)
                    ->where('user_id', $actor->id)
                    ->exists()
            ) {
                throw new ValidationException(['redPacket' => '你已经领取过这个红包。']);
            }

            $amount = $this->nextClaimAmount($packet);

            $this->points->award(
                $actor,
                $amount,
                'red_packet.claim',
                'doingfb-red-packet',
                (int) $packet->id
            );

            RedPacketClaim::query()->create([
                'red_packet_id' => (int) $packet->id,
                'user_id' => (int) $actor->id,
                'amount' => $amount,
            ]);

            $packet->claimed_amount = (int) $packet->claimed_amount + $amount;
            $packet->claimed_count = (int) $packet->claimed_count + 1;
            $packet->save();

            return $packet;
        });

        if ($expired) {
            throw new ValidationException(['redPacket' => '红包已过期，剩余积分已退回发起人。']);
        }

        return $this->findOrFail((int) $packet->id);
    }

    public function cancelUnpublished(User $actor, int $packetId): void
    {
        $actor->assertRegistered();

        $this->db->transaction(function () use ($actor, $packetId): void {
            /** @var RedPacket|null $packet */
            $packet = RedPacket::query()
                ->whereKey($packetId)
                ->lockForUpdate()
                ->first();

            if (!$packet || $packet->refunded_at !== null || $packet->published_at !== null) {
                return;
            }

            if ((int) $packet->user_id !== (int) $actor->id) {
                throw new PermissionDeniedException();
            }

            $this->refundLocked($packet);
        });
    }

    public function publishFromContent(string $content, int $userId): void
    {
        if (!preg_match_all(
            '/\[redpacket\s+id=(\d+)\]|\[\[doingfb-red-packet:(\d+)\]\]/i',
            $content,
            $matches
        )) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_merge($matches[1], $matches[2]))));

        if (!$ids) {
            return;
        }

        RedPacket::query()
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->whereNull('published_at')
            ->whereNull('refunded_at')
            ->update([
                'published_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function refundExpired(int $limit = 100): int
    {
        $ids = RedPacket::query()
            ->whereNull('refunded_at')
            ->whereColumn('claimed_count', '<', 'total_count')
            ->where('expires_at', '<=', Carbon::now())
            ->limit($limit)
            ->pluck('id')
            ->all();

        return $this->refundIds($ids, true);
    }

    public function refundStaleUnpublished(int $minutes = 60, int $limit = 100): int
    {
        $ids = RedPacket::query()
            ->whereNull('published_at')
            ->whereNull('refunded_at')
            ->where(function ($query) use ($minutes) {
                $query
                    ->whereNull('created_at')
                    ->orWhere('created_at', '<=', Carbon::now()->subMinutes($minutes));
            })
            ->limit($limit)
            ->pluck('id')
            ->all();

        return $this->refundIds($ids, false);
    }

    private function assertCanCreate(User $actor): void
    {
        if (
            !$this->settings->enabled()
            || !$this->settings->pointSystemEnabled()
            || !$actor->hasPermission('doingfb-red-packet.create')
        ) {
            throw new PermissionDeniedException();
        }
    }

    private function assertCanClaim(User $actor): void
    {
        if (
            !$this->settings->enabled()
            || !$this->settings->pointSystemEnabled()
            || !$actor->hasPermission('doingfb-red-packet.claim')
        ) {
            throw new PermissionDeniedException();
        }
    }

    private function validateAmount(int $totalAmount, int $totalCount, string $distribution): void
    {
        if (
            $totalAmount < $this->settings->minAmount()
            || $totalAmount > $this->settings->maxAmount()
        ) {
            throw new ValidationException([
                'totalAmount' => sprintf(
                    '红包总积分必须在 %d 到 %d 之间。',
                    $this->settings->minAmount(),
                    $this->settings->maxAmount()
                ),
            ]);
        }

        if ($totalCount > $this->settings->maxCount()) {
            throw new ValidationException([
                'totalCount' => sprintf(
                    '红包个数最多为 %d 个。',
                    $this->settings->maxCount()
                ),
            ]);
        }

        if ($totalAmount < $totalCount) {
            throw new ValidationException([
                'totalAmount' => '每个红包至少需要 1 积分，总积分不能少于红包个数。',
            ]);
        }

    }

    private function nextClaimAmount(RedPacket $packet): int
    {
        $remainingCount = max(1, (int) $packet->total_count - (int) $packet->claimed_count);
        $remainingAmount = $packet->remainingAmount();

        if ($packet->distribution === 'random') {
            $precomputedAmount = $this->precomputedClaimAmount($packet);

            if ($precomputedAmount !== null) {
                return $precomputedAmount;
            }

            return random_int(1, $remainingAmount - $remainingCount + 1);
        }

        $base = intdiv($remainingAmount, $remainingCount);
        $remainder = $remainingAmount % $remainingCount;

        return $base + ((int) $packet->claimed_count < $remainder ? 1 : 0);
    }

    private function buildRandomClaimAmounts(int $totalAmount, int $totalCount): array
    {
        $amounts = array_fill(0, $totalCount, 1);
        $remaining = $totalAmount - $totalCount;

        for ($index = 0; $index < $remaining; $index++) {
            $amounts[random_int(0, $totalCount - 1)]++;
        }

        for ($index = count($amounts) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$amounts[$index], $amounts[$swap]] = [$amounts[$swap], $amounts[$index]];
        }

        return $amounts;
    }

    private function precomputedClaimAmount(RedPacket $packet): ?int
    {
        $amounts = $packet->random_amounts;
        $index = (int) $packet->claimed_count;

        if (!is_array($amounts) || count($amounts) !== (int) $packet->total_count) {
            return null;
        }

        return isset($amounts[$index]) && (int) $amounts[$index] > 0
            ? (int) $amounts[$index]
            : null;
    }

    private function refundIds(array $ids, bool $requireExpired): int
    {
        $count = 0;

        foreach ($ids as $id) {
            $refunded = $this->db->transaction(function () use ($id, $requireExpired): bool {
                /** @var RedPacket|null $packet */
                $packet = RedPacket::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$packet || $packet->refunded_at !== null) {
                    return false;
                }

                if ($requireExpired && !$packet->isExpired()) {
                    return false;
                }

                $this->refundLocked($packet);

                return true;
            });

            $count += (int) $refunded;
        }

        return $count;
    }

    private function refundLocked(RedPacket $packet): void
    {
        $remaining = $packet->remainingAmount();
        $packet->refunded_at = Carbon::now();
        $packet->updated_at = Carbon::now();
        $packet->save();

        if ($remaining <= 0) {
            return;
        }

        $sender = User::query()->findOrFail($packet->user_id);

        $this->points->award(
            $sender,
            $remaining,
            'red_packet.refund',
            'doingfb-red-packet',
            (int) $packet->id
        );
    }
}

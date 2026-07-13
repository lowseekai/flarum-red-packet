<?php

namespace Doingfb\RedPacket\Support;

use AntoineFr\Money\Event\MoneyUpdated;
use Doingfb\RedPacket\Model\RedPacket;
use Doingfb\RedPacket\Model\RedPacketClaim;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

class RedPacketRepository
{
    public function __construct(
        private ConnectionInterface $db,
        private Dispatcher $events,
        private RedPacketSettings $settings
    ) {
    }

    public function findOrFail(int $id): RedPacket
    {
        return RedPacket::query()
            ->with(['user', 'claims.user'])
            ->findOrFail($id);
    }

    public function create(User $actor, float $totalAmount, int $totalCount, string $greeting): RedPacket
    {
        $actor->assertRegistered();

        if (!$this->settings->enabled() || !$actor->hasPermission('doingfb-red-packet.create')) {
            throw new PermissionDeniedException();
        }

        $totalAmount = round($totalAmount, 4);
        $totalCount = max(1, $totalCount);
        $greeting = trim(mb_substr($greeting, 0, 120));

        if ($totalAmount < $this->settings->minAmount() || $totalAmount > $this->settings->maxAmount()) {
            throw new ValidationException([
                'totalAmount' => sprintf('红包金额必须在 %s 到 %s 之间。', $this->settings->minAmount(), $this->settings->maxAmount()),
            ]);
        }

        if ($totalCount > $this->settings->maxCount()) {
            throw new ValidationException([
                'totalCount' => sprintf('红包个数最多 %d 个。', $this->settings->maxCount()),
            ]);
        }

        if ($totalAmount / $totalCount < 0.0001) {
            throw new ValidationException([
                'totalAmount' => '单个红包金额过低，请减少个数或提高总金额。',
            ]);
        }

        $updatedActor = null;
        $packet = $this->db->transaction(function () use ($actor, $totalAmount, $totalCount, $greeting, &$updatedActor) {
            /** @var User $lockedActor */
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            if ((float) $lockedActor->money < $totalAmount) {
                throw new ValidationException([
                    'totalAmount' => '余额不足，无法发送红包。',
                ]);
            }

            $lockedActor->money = round((float) $lockedActor->money - $totalAmount, 4);
            $lockedActor->save();
            $updatedActor = $lockedActor;

            $now = Carbon::now();

            $packet = new RedPacket();
            $packet->user_id = (int) $lockedActor->id;
            $packet->total_amount = $totalAmount;
            $packet->total_count = $totalCount;
            $packet->claimed_amount = 0;
            $packet->claimed_count = 0;
            $packet->distribution = 'average';
            $packet->greeting = $greeting !== '' ? $greeting : '恭喜发财，大吉大利';
            $packet->expires_at = $now->copy()->addMinutes($this->settings->expiresMinutes());
            $packet->published_at = null;
            $packet->created_at = $now;
            $packet->updated_at = $now;
            $packet->save();

            return $packet;
        });

        if ($updatedActor) {
            $this->events->dispatch(new MoneyUpdated($updatedActor));
        }

        return $this->findOrFail((int) $packet->id);
    }

    public function claim(User $actor, int $packetId): RedPacket
    {
        $actor->assertRegistered();

        if (!$this->settings->enabled() || !$actor->hasPermission('doingfb-red-packet.claim')) {
            throw new PermissionDeniedException();
        }

        $updatedUser = null;
        $updatedSender = null;
        $expired = false;

        $packet = $this->db->transaction(function () use ($actor, $packetId, &$updatedUser, &$updatedSender, &$expired) {
            /** @var RedPacket $packet */
            $packet = RedPacket::query()->whereKey($packetId)->lockForUpdate()->firstOrFail();

            if ($packet->refunded_at !== null) {
                throw new ValidationException(['redPacket' => '红包已退款。']);
            }

            if ($packet->published_at === null) {
                throw new ValidationException(['redPacket' => '红包尚未发布。']);
            }

            if ($packet->isExpired()) {
                $updatedSender = $this->refundLocked($packet);
                $expired = true;

                return $packet;
            }

            if ($packet->isFullyClaimed()) {
                throw new ValidationException(['redPacket' => '红包已经被领完。']);
            }

            if (RedPacketClaim::query()->where('red_packet_id', $packet->id)->where('user_id', $actor->id)->exists()) {
                throw new ValidationException(['redPacket' => '你已经领取过这个红包。']);
            }

            $amount = $this->nextClaimAmount($packet);

            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $lockedUser->money = round((float) $lockedUser->money + $amount, 4);
            $lockedUser->save();
            $updatedUser = $lockedUser;

            $claim = new RedPacketClaim();
            $claim->red_packet_id = (int) $packet->id;
            $claim->user_id = (int) $lockedUser->id;
            $claim->amount = $amount;
            $claim->save();

            $packet->claimed_amount = round((float) $packet->claimed_amount + $amount, 4);
            $packet->claimed_count = (int) $packet->claimed_count + 1;
            $packet->save();

            return $packet;
        });

        if ($updatedUser) {
            $this->events->dispatch(new MoneyUpdated($updatedUser));
        }

        if ($updatedSender) {
            $this->events->dispatch(new MoneyUpdated($updatedSender));
        }

        if ($expired) {
            throw new ValidationException(['redPacket' => '红包已过期。']);
        }

        return $this->findOrFail((int) $packet->id);
    }

    public function cancelUnpublished(User $actor, int $packetId): void
    {
        $actor->assertRegistered();

        $updatedSender = null;

        $this->db->transaction(function () use ($actor, $packetId, &$updatedSender) {
            /** @var RedPacket|null $packet */
            $packet = RedPacket::query()->whereKey($packetId)->lockForUpdate()->first();

            if (!$packet || $packet->refunded_at !== null || $packet->published_at !== null) {
                return;
            }

            if ((int) $packet->user_id !== (int) $actor->id) {
                throw new PermissionDeniedException();
            }

            $updatedSender = $this->refundLocked($packet);
        });

        if ($updatedSender) {
            $this->events->dispatch(new MoneyUpdated($updatedSender));
        }
    }

    public function publishFromContent(string $content, int $userId): void
    {
        if (!preg_match_all('/\[redpacket\s+id=(\d+)\]|\[\[doingfb-red-packet:(\d+)\]\]/i', $content, $matches)) {
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

        $count = 0;

        foreach ($ids as $id) {
            $updatedSender = null;

            $this->db->transaction(function () use ($id, &$updatedSender) {
                /** @var RedPacket|null $packet */
                $packet = RedPacket::query()->whereKey($id)->lockForUpdate()->first();

                if (!$packet || $packet->refunded_at !== null || !$packet->isExpired()) {
                    return;
                }

                $updatedSender = $this->refundLocked($packet);
            });

            if ($updatedSender) {
                $count++;
                $this->events->dispatch(new MoneyUpdated($updatedSender));
            }
        }

        return $count;
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

        $count = 0;

        foreach ($ids as $id) {
            $updatedSender = null;

            $this->db->transaction(function () use ($id, &$updatedSender) {
                /** @var RedPacket|null $packet */
                $packet = RedPacket::query()->whereKey($id)->lockForUpdate()->first();

                if (!$packet || $packet->refunded_at !== null || $packet->published_at !== null) {
                    return;
                }

                $updatedSender = $this->refundLocked($packet);
            });

            if ($updatedSender) {
                $count++;
                $this->events->dispatch(new MoneyUpdated($updatedSender));
            }
        }

        return $count;
    }

    private function nextClaimAmount(RedPacket $packet): float
    {
        $remainingCount = max(1, (int) $packet->total_count - (int) $packet->claimed_count);
        $remainingAmount = $packet->remainingAmount();

        if ($remainingCount === 1) {
            return round($remainingAmount, 4);
        }

        return round(floor(($remainingAmount / $remainingCount) * 10000) / 10000, 4);
    }

    private function refundLocked(RedPacket $packet): ?User
    {
        $remaining = $packet->remainingAmount();
        $packet->refunded_at = Carbon::now();
        $packet->updated_at = Carbon::now();
        $packet->save();

        if ($remaining <= 0) {
            return null;
        }

        /** @var User $sender */
        $sender = User::query()->whereKey($packet->user_id)->lockForUpdate()->firstOrFail();
        $sender->money = round((float) $sender->money + $remaining, 4);
        $sender->save();

        return $sender;
    }
}

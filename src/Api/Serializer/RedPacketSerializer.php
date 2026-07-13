<?php

namespace Doingfb\RedPacket\Api\Serializer;

use Doingfb\RedPacket\Model\RedPacket;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\UserSerializer;

class RedPacketSerializer extends AbstractSerializer
{
    protected $type = 'doingfb-red-packets';

    /**
     * @param RedPacket $packet
     */
    protected function getDefaultAttributes($packet): array
    {
        $actor = $this->getActor();
        $claims = $packet->relationLoaded('claims') ? $packet->claims : collect();
        $actorClaim = $actor && !$actor->isGuest() ? $claims->firstWhere('user_id', $actor->id) : null;

        return [
            'userId' => (int) $packet->user_id,
            'totalAmount' => (float) $packet->total_amount,
            'totalCount' => (int) $packet->total_count,
            'claimedAmount' => (float) $packet->claimed_amount,
            'claimedCount' => (int) $packet->claimed_count,
            'remainingAmount' => $packet->remainingAmount(),
            'distribution' => $packet->distribution,
            'greeting' => $packet->greeting,
            'status' => $packet->status(),
            'claimedByActor' => $actorClaim !== null,
            'actorClaimAmount' => $actorClaim ? (float) $actorClaim->amount : null,
            'canClaim' => $actor
                && !$actor->isGuest()
                && $actor->hasPermission('doingfb-red-packet.claim')
                && !$actorClaim
                && $packet->status() === 'open',
            'expiresAt' => $this->formatDate($packet->expires_at),
            'refundedAt' => $this->formatDate($packet->refunded_at),
            'createdAt' => $this->formatDate($packet->created_at),
            'updatedAt' => $this->formatDate($packet->updated_at),
        ];
    }

    public function user(RedPacket $packet)
    {
        return $this->hasOne($packet, UserSerializer::class);
    }
}

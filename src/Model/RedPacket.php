<?php

namespace Doingfb\RedPacket\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedPacket extends AbstractModel
{
    protected $table = 'red_packets';

    protected $casts = [
        'total_amount' => 'float',
        'claimed_amount' => 'float',
        'total_count' => 'integer',
        'claimed_count' => 'integer',
        'random_amounts' => 'array',
        'expires_at' => 'datetime',
        'refunded_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(RedPacketClaim::class, 'red_packet_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isFullyClaimed(): bool
    {
        return $this->claimed_count >= $this->total_count;
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->claimed_amount, 4));
    }

    public function status(): string
    {
        if ($this->refunded_at !== null) {
            return 'refunded';
        }

        if ($this->published_at === null) {
            return 'pending';
        }

        if ($this->isFullyClaimed()) {
            return 'claimed';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'open';
    }
}

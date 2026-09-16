<?php

namespace Doingfb\RedPacket\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedPacketClaim extends AbstractModel
{
    protected $table = 'red_packet_claims';

    protected $fillable = [
        'red_packet_id',
        'user_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function packet(): BelongsTo
    {
        return $this->belongsTo(RedPacket::class, 'red_packet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

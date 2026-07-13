<?php

namespace Doingfb\RedPacket;

use Doingfb\RedPacket\Api\Controller\ClaimRedPacketController;
use Doingfb\RedPacket\Api\Controller\CreateRedPacketController;
use Doingfb\RedPacket\Api\Controller\ShowRedPacketController;
use Doingfb\RedPacket\Console\RefundExpiredRedPacketsCommand;
use Doingfb\RedPacket\Model\RedPacket;
use Doingfb\RedPacket\Support\RedPacketSettings;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Console())
        ->command(RefundExpiredRedPacketsCommand::class)
        ->schedule(RefundExpiredRedPacketsCommand::class, function (Event $event) {
            $event->everyFifteenMinutes();
        }),

    (new Extend\Routes('api'))
        ->get('/doingfb-red-packets/{id}', 'doingfb.red-packets.show', ShowRedPacketController::class)
        ->post('/doingfb-red-packets', 'doingfb.red-packets.create', CreateRedPacketController::class)
        ->post('/doingfb-red-packets/{id}/claim', 'doingfb.red-packets.claim', ClaimRedPacketController::class),

    (new Extend\Model(RedPacket::class))
        ->relationship('claims', function (RedPacket $packet) {
            return $packet->hasMany(Model\RedPacketClaim::class, 'red_packet_id');
        }),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(function (ForumSerializer $serializer) {
            $actor = $serializer->getActor();
            $settings = resolve(RedPacketSettings::class);

            return [
                'doingfb-red-packet.enabled' => $settings->enabled(),
                'doingfb-red-packet.minAmount' => $settings->minAmount(),
                'doingfb-red-packet.maxAmount' => $settings->maxAmount(),
                'doingfb-red-packet.maxCount' => $settings->maxCount(),
                'doingfb-red-packet.expiresMinutes' => $settings->expiresMinutes(),
                'doingfb-red-packet.canCreate' => !$actor->isGuest() && $actor->hasPermission('doingfb-red-packet.create'),
                'doingfb-red-packet.canClaim' => !$actor->isGuest() && $actor->hasPermission('doingfb-red-packet.claim'),
                'antoinefr-money.moneyname' => resolve('flarum.settings')->get('antoinefr-money.moneyname', '[money]'),
            ];
        }),

    (new Extend\Settings())
        ->default('doingfb-red-packet.enabled', true)
        ->default('doingfb-red-packet.min_amount', 1)
        ->default('doingfb-red-packet.max_amount', 1000)
        ->default('doingfb-red-packet.max_count', 50)
        ->default('doingfb-red-packet.expires_minutes', 1440),
];

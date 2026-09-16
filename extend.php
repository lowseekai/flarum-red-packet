<?php

namespace Doingfb\RedPacket;

use Doingfb\RedPacket\Api\Resource\RedPacketResource;
use Doingfb\RedPacket\Api\Resource\RedPacketClaimResource;
use Doingfb\RedPacket\Console\RefundExpiredRedPacketsCommand;
use Doingfb\RedPacket\Formatter\ConfigureRedPacketFormatter;
use Doingfb\RedPacket\Listener\PublishRedPacketsInPost;
use Doingfb\RedPacket\Support\RedPacketSettings;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Illuminate\Console\Scheduling\Event;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Formatter())
        ->configure(ConfigureRedPacketFormatter::class),

    new Extend\ApiResource(RedPacketResource::class),
    new Extend\ApiResource(RedPacketClaimResource::class),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('hasRedPacket')
                ->get(function (Discussion $discussion): bool {
                    $content = (string) ($discussion->firstPost?->content ?? '');

                    return (bool) preg_match(
                        '/\[redpacket\s+id=\d+\]|\[\[doingfb-red-packet:\d+\]\]/i',
                        $content
                    );
                }),
        ]),

    (new Extend\Console())
        ->command(RefundExpiredRedPacketsCommand::class)
        ->schedule(RefundExpiredRedPacketsCommand::class, function (Event $event) {
            $event->everyFifteenMinutes();
        }),

    (new Extend\Event())
        ->listen(Posted::class, PublishRedPacketsInPost::class)
        ->listen(Revised::class, PublishRedPacketsInPost::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(function () {
            return [
                \Flarum\Api\Schema\Boolean::make('redPacketEnabled')
                    ->get(fn () => resolve(RedPacketSettings::class)->enabled()),
                \Flarum\Api\Schema\Integer::make('redPacketMinAmount')
                    ->get(fn () => resolve(RedPacketSettings::class)->minAmount()),
                \Flarum\Api\Schema\Integer::make('redPacketMaxAmount')
                    ->get(fn () => resolve(RedPacketSettings::class)->maxAmount()),
                \Flarum\Api\Schema\Integer::make('redPacketMaxCount')
                    ->get(fn () => resolve(RedPacketSettings::class)->maxCount()),
                \Flarum\Api\Schema\Str::make('redPacketCurrencyName')
                    ->get(fn () => resolve(RedPacketSettings::class)->currencyName()),
                \Flarum\Api\Schema\Boolean::make('canCreateRedPacket')
                    ->get(function ($forum, \Flarum\Api\Context $context) {
                        $actor = $context->getActor();

                        return !$actor->isGuest()
                            && resolve(RedPacketSettings::class)->enabled()
                            && resolve(RedPacketSettings::class)->pointSystemEnabled()
                            && $actor->hasPermission('doingfb-red-packet.create');
                    }),
                \Flarum\Api\Schema\Boolean::make('canClaimRedPacket')
                    ->get(function ($forum, \Flarum\Api\Context $context) {
                        $actor = $context->getActor();

                        return !$actor->isGuest()
                            && resolve(RedPacketSettings::class)->enabled()
                            && resolve(RedPacketSettings::class)->pointSystemEnabled()
                            && $actor->hasPermission('doingfb-red-packet.claim');
                    }),
            ];
        }),

    (new Extend\Settings())
        ->default('doingfb-red-packet.enabled', true)
        ->default('doingfb-red-packet.min_amount', 1)
        ->default('doingfb-red-packet.max_amount', 1000)
        ->default('doingfb-red-packet.max_count', 50)
        ->default('doingfb-red-packet.expires_minutes', 1440),
];

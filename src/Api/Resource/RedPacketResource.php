<?php

namespace Doingfb\RedPacket\Api\Resource;

use Doingfb\RedPacket\Model\RedPacket;
use Doingfb\RedPacket\Support\RedPacketRepository;
use Doingfb\RedPacket\Support\RedPacketSettings;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Laminas\Diactoros\Response\EmptyResponse;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractDatabaseResource<RedPacket>
 */
class RedPacketResource extends AbstractDatabaseResource
{
    public function __construct(
        protected RedPacketRepository $packets,
        protected RedPacketSettings $settings
    ) {
    }

    public function type(): string
    {
        return 'doingfb-red-packets';
    }

    public function model(): string
    {
        return RedPacket::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $query->with(['user', 'claims']);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Create::make()
                ->authenticated()
                ->can('doingfb-red-packet.create'),
            Endpoint\Endpoint::make('claim')
                ->route('POST', '/{id}/claim')
                ->authenticated()
                ->can('doingfb-red-packet.claim')
                ->action(function (Context $context): RedPacket {
                    return $this->packets->claim(
                        $context->getActor(),
                        (int) $context->model->getKey()
                    );
                }),
            Endpoint\Endpoint::make('cancel')
                ->route('DELETE', '/{id}')
                ->authenticated()
                ->action(function (Context $context): ?RedPacket {
                    $this->packets->cancelUnpublished(
                        $context->getActor(),
                        (int) $context->model->getKey()
                    );

                    return null;
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('userId')
                ->property('user_id'),
            Schema\Integer::make('totalAmount')
                ->property('total_amount')
                ->writableOnCreate()
                ->requiredOnCreate(),
            Schema\Integer::make('totalCount')
                ->property('total_count')
                ->writableOnCreate()
                ->requiredOnCreate(),
            Schema\Integer::make('claimedAmount')
                ->property('claimed_amount'),
            Schema\Integer::make('claimedCount')
                ->property('claimed_count'),
            Schema\Integer::make('remainingAmount')
                ->get(fn (RedPacket $packet) => $packet->remainingAmount()),
            Schema\Str::make('distribution')
                ->writableOnCreate(),
            Schema\Str::make('greeting')
                ->writableOnCreate(),
            Schema\Str::make('status')
                ->get(fn (RedPacket $packet) => $packet->status()),
            Schema\Boolean::make('claimedByActor')
                ->get(function (RedPacket $packet, Context $context): bool {
                    $actor = $context->getActor();

                    return !$actor->isGuest()
                        && $packet->claims->contains('user_id', $actor->id);
                }),
            Schema\Integer::make('actorClaimAmount')
                ->nullable()
                ->get(function (RedPacket $packet, Context $context): ?int {
                    $actor = $context->getActor();

                    return !$actor->isGuest()
                        ? ($packet->claims->firstWhere('user_id', $actor->id)?->amount)
                        : null;
                }),
            Schema\Boolean::make('canClaim')
                ->get(function (RedPacket $packet, Context $context): bool {
                    $actor = $context->getActor();

                    return !$actor->isGuest()
                        && $this->settings->enabled()
                        && $this->settings->pointSystemEnabled()
                        && $actor->hasPermission('doingfb-red-packet.claim')
                        && !$packet->claims->contains('user_id', $actor->id)
                        && $packet->status() === 'open';
                }),
            Schema\DateTime::make('expiresAt')
                ->property('expires_at'),
            Schema\DateTime::make('refundedAt')
                ->property('refunded_at'),
            Schema\DateTime::make('publishedAt')
                ->property('published_at'),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function newModel(\Tobyz\JsonApiServer\Context $context): object
    {
        $packet = new RedPacket();
        $packet->distribution = 'average';
        $packet->greeting = '';

        return $packet;
    }

    public function create(object $model, \Tobyz\JsonApiServer\Context $context): object
    {
        /** @var RedPacket $model */
        return $this->packets->create(
            $context->getActor(),
            (int) $model->total_amount,
            (int) $model->total_count,
            (string) $model->distribution,
            (string) $model->greeting
        );
    }
}

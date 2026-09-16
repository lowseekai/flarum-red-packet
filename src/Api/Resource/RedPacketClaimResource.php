<?php

namespace Doingfb\RedPacket\Api\Resource;

use Doingfb\RedPacket\Model\RedPacketClaim;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractDatabaseResource<RedPacketClaim>
 */
class RedPacketClaimResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'doingfb-red-packet-claims';
    }

    public function model(): string
    {
        return RedPacketClaim::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $query->whereHas('packet', function (Builder $packets): void {
            $packets->whereNotNull('published_at');
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->defaultInclude(['user']),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('redPacketId')
                ->property('red_packet_id'),
            Schema\Integer::make('userId')
                ->property('user_id'),
            Schema\Integer::make('amount'),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }
}

<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets') || !$schema->hasTable('red_packet_claims')) {
            return;
        }

        $connection = $schema->getConnection();

        // Flarum's AbstractModel disables automatic timestamps. Repair records
        // created before the extension began assigning them explicitly.
        $connection->statement(
            'UPDATE red_packets
             SET created_at = COALESCE(created_at, published_at, CURRENT_TIMESTAMP),
                 updated_at = COALESCE(updated_at, created_at, published_at, CURRENT_TIMESTAMP)
             WHERE created_at IS NULL OR updated_at IS NULL'
        );

        $connection->statement(
            'UPDATE red_packet_claims AS claims
             LEFT JOIN red_packets AS packets ON packets.id = claims.red_packet_id
             SET claims.created_at = COALESCE(claims.created_at, packets.created_at, packets.published_at, CURRENT_TIMESTAMP),
                 claims.updated_at = COALESCE(claims.updated_at, claims.created_at, packets.created_at, packets.published_at, CURRENT_TIMESTAMP)
             WHERE claims.created_at IS NULL OR claims.updated_at IS NULL'
        );
    },
    'down' => function (Builder $schema) {
        // Timestamps are data, so this migration intentionally does not erase
        // repaired values when rolled back.
    },
];

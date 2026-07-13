<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets') || $schema->hasColumn('red_packets', 'published_at')) {
            return;
        }

        $schema->table('red_packets', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('refunded_at');
            $table->index('published_at');
        });

        $connection = $schema->getConnection();
        $connection->statement('UPDATE red_packets SET published_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE published_at IS NULL');
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets') || !$schema->hasColumn('red_packets', 'published_at')) {
            return;
        }

        $schema->table('red_packets', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    },
];

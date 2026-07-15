<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets') || $schema->hasColumn('red_packets', 'random_amounts')) {
            return;
        }

        $schema->table('red_packets', function (Blueprint $table) {
            $table->text('random_amounts')->nullable()->after('distribution');
        });
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets') || !$schema->hasColumn('red_packets', 'random_amounts')) {
            return;
        }

        $schema->table('red_packets', function (Blueprint $table) {
            $table->dropColumn('random_amounts');
        });
    },
];

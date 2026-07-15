<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('red_packets')) {
            $schema->create('red_packets', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->decimal('total_amount', 20, 4);
                $table->unsignedInteger('total_count');
                $table->decimal('claimed_amount', 20, 4)->default(0);
                $table->unsignedInteger('claimed_count')->default(0);
                $table->string('distribution', 20)->default('average');
                $table->text('random_amounts')->nullable();
                $table->string('greeting', 120)->default('');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('expires_at');
                $table->index(['claimed_count', 'total_count']);
            });
        }

        if (!$schema->hasTable('red_packet_claims')) {
            $schema->create('red_packet_claims', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('red_packet_id');
                $table->unsignedInteger('user_id');
                $table->decimal('amount', 20, 4);
                $table->timestamps();

                $table->unique(['red_packet_id', 'user_id']);
                $table->index('user_id');
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('red_packet_claims');
        $schema->dropIfExists('red_packets');
    },
];

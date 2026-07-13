<?php

use Flarum\Group\Group;
use Illuminate\Database\Schema\Builder;

$rows = [
    ['permission' => 'doingfb-red-packet.create', 'group_id' => Group::MEMBER_ID],
    ['permission' => 'doingfb-red-packet.claim', 'group_id' => Group::MEMBER_ID],
];

return [
    'up' => function (Builder $schema) use ($rows) {
        $db = $schema->getConnection();

        foreach ($rows as $row) {
            if ($db->table('groups')->where('id', $row['group_id'])->doesntExist()) {
                continue;
            }

            if ($db->table('group_permission')->where($row)->doesntExist()) {
                $db->table('group_permission')->insert($row);
            }
        }
    },

    'down' => function (Builder $schema) use ($rows) {
        $db = $schema->getConnection();

        foreach ($rows as $row) {
            $db->table('group_permission')->where($row)->delete();
        }
    },
];

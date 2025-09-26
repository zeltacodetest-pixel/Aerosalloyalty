<?php
$schemas['aerosalloyalty_campaign_types'] = [
    'aerosalloyalty_campaign_type_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => false
    ],
    'code' => [
        'type' => 'varchar(32)',
        'is_null' => false
    ],
    'name' => [
        'type' => 'varchar(100)',
        'is_null' => false
    ],
    'icon' => [
        'type' => 'varchar(255)',
        'is_null' => true,
        'default' => null
    ],
    'created_at' => [
        'type' => 'datetime',
        'default' => 'CURRENT_TIMESTAMP'
    ],
    'updated_at' => [
        'type' => 'datetime',
        'default' => 'CURRENT_TIMESTAMP',
        'on_update' => 'CURRENT_TIMESTAMP'
    ]
];

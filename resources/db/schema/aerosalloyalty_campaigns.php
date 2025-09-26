<?php
$schemas['aerosalloyalty_campaigns'] = [
    'aerosalloyalty_campaign_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => false
    ],
    'card_number' => [
        'type' => 'varchar(64)',
        'is_null' => false
    ],
    'campaign_uid' => [
        'type' => 'varchar(64)',
        'is_null' => false
    ],
    'campaign_type_code' => [
        'type' => 'varchar(32)',
        'is_null' => false
    ],
    'name' => [
        'type' => 'varchar(150)',
        'is_null' => false
    ],
    'points_balance' => [
        'type' => 'int(11)',
        'default' => '0'
    ],
    'prizes' => [
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

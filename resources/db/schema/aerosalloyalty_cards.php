<?php
$schemas['aerosalloyalty_cards'] = [
    'aerosalloyalty_card_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => false
    ],
    'customer_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => true,
        'default' => null
    ],
    'card_number' => [
        'type' => 'varchar(64)',
        'is_null' => false
    ],
    'ean_encoding' => [
        'type' => 'varchar(32)',
        'default' => 'EAN13'
    ],
    'is_virtual' => [
        'type' => 'tinyint(1)',
        'default' => '0'
    ],
    'deleted_at' => [
        'type' => 'datetime',
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

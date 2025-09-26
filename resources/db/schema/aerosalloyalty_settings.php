<?php
$schemas['aerosalloyalty_settings'] = [
    'aerosalloyalty_settings_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'app_id' => [
        'type' => 'int(11) unsigned',
        'default' => '0',
        'is_null' => false
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'default' => '0',
        'is_null' => false
    ],
    'default_ean_encoding' => [
        'type' => 'varchar(32)',
        'default' => 'EAN13'
    ],
    'enable_check_benefits' => [
        'type' => 'tinyint(1)',
        'default' => '1'
    ],
    'webhook_url' => [
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

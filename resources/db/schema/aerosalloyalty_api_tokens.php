<?php
$schemas['aerosalloyalty_api_tokens'] = [
    'aerosalloyalty_api_token_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => false
    ],
    'token' => [
        'type' => 'varchar(128)',
        'is_null' => false
    ],
    'is_active' => [
        'type' => 'tinyint(1)',
        'default' => '1'
    ],
    'last_used_at' => [
        'type' => 'datetime',
        'is_null' => true,
        'default' => null
    ],
    'created_at' => [
        'type' => 'datetime',
        'default' => 'CURRENT_TIMESTAMP'
    ]
];

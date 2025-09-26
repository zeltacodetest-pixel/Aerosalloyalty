<?php

$schemas['aerosalloyalty_webhook_logs'] = [
    'aerosalloyalty_webhook_log_id' => [
        'type' => 'bigint(20) unsigned',
        'auto_increment' => true,
        'primary' => true
    ],
    'value_id' => [
        'type' => 'int(11) unsigned',
        'is_null' => false
    ],
    'direction' => [
        'type' => "enum('outbound','inbound')",
        'is_null' => false
    ],
    'endpoint' => [
        'type' => 'varchar(255)',
        'is_null' => false
    ],
    'http_status' => [
        'type' => 'int(11)',
        'is_null' => true,
        'default' => null
    ],
    'payload' => [
        'type' => 'mediumtext',
        'is_null' => true
    ],
    'created_at' => [
        'type' => 'datetime',
        'default' => 'CURRENT_TIMESTAMP'
    ]
];

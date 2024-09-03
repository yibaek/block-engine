<?php
use Monolog\Logger;
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // logger settings
        'logger' => [
            'name' => 'synctree-tool',
            'path' => __DIR__ . '/../../logs/',
            'level' => Logger::DEBUG
        ],

        // secure file info
        'secure' => [
            'file_path' => __DIR__ . '/../../../secure/config.ini'
        ],

        // credentials file info
        'credentials' => [
            'file_path' => '/home/ubuntu/.synctree/credentials'
        ],

        // shared storage
        'userFileStore' => [
            'path' => '/home/ubuntu/shared/',
        ],

        // contents file info
        'contents' => [
            'file_path' => __DIR__ . '/../../contents/'
        ],

        // s3 common settings
        's3' => [],

        // dynamodb common settings
        'dynamo' => [
            'crypt' => true,
            'compress' => true
        ],

        // redis common settings
        'redis' => [
            'read_timeout' => 1.5,
            'connection_timeout' => 1.5,
            'cluster' => false,
            'crypt' => true,
            'compress' => [
                'is_compress' => true,
                'lists' => [
                ]
            ]
        ],

        // rdb common settings
        'rdb' => [
            'driver' => 'mysql',
            'studio' => [
                'dbname' => 'staging_synctree_studio',
                'charset' => 'utf8'
            ],
            'log' => [
                'dbname' => 'staging_synctree_studio_logdb',
                'charset' => 'utf8'
            ],
            'auth' => [
                'dbname' => 'staging_synctree_auth',
                'charset' => 'utf8'
            ],
            'plan' => [
                'dbname' => 'staging_synctree_plan',
                'charset' => 'utf8'
            ],
            'portal' => [
                'dbname' => 'staging_synctree_portal',
                'charset' => 'utf8'
            ]
        ],

        // storage encrypt key
        'storage' => [
            'db_key' => '4288a4179b65d019'
        ]
    ]
];

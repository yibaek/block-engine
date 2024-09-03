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
            'file_path' => __DIR__ . '/secure.ini'
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
            'crypt' => false,
            'compress' => false
        ],

        // redis common settings
        'redis' => [
            'read_timeout' => 1.5,
            'connection_timeout' => 1.5,
            'cluster' => false,
            'crypt' => false,
            'compress' => [
                'is_compress' => false,
                'lists' => [
                ]
            ]
        ],

        // rdb common settings
        'rdb' => [
            'driver' => 'mysql',
            'studio' => [
                'dbname' => 'synctree_studio',
                'charset' => 'utf8'
            ],
            'log' => [
                'dbname' => 'synctree_studio_logdb',
                'charset' => 'utf8'
            ],
            'auth' => [
                'dbname' => 'synctree_auth',
                'charset' => 'utf8'
            ],
            'plan' => [
                'dbname' => 'synctree_plan',
                'charset' => 'utf8'
            ],
            'portal' => [
                'dbname' => 'synctree_portal',
                'charset' => 'utf8'
            ]
        ],

        // storage encrypt key
        'storage' => [
            'db_key' => 'dummy-storage-encryption-key'
        ]
    ]
];

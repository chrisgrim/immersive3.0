<?php

declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTIC_HOST', 'localhost:9200'),
            ],
            // Without these a hung cluster holds a request for as long as it
            // hangs, and a secrecy save writes the index inline. The first
            // timeout ends the call: the one node is then marked down.
            'httpClientOptions' => [
                'timeout' => 10,
                'connect_timeout' => 2,
            ],
        ],
    ],
];

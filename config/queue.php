<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'null'),
    ],
];

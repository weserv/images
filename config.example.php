<?php

return [
    'name' => 'API 3 - GitHub, DEMO',
    'url' => 'images.weserv.nl',
    'exampleImage' => 'rbx.weserv.nl/lichtenstein.jpg',
    'exampleTransparentImage' => 'rbx.weserv.nl/transparency_demo.png',
    'exampleSmartcropImage' => 'ssl:upload.wikimedia.org/wikipedia/commons/4/45/Equus_quagga_burchellii_-_Etosha%2C_2014.jpg',
    // Default to Memcached
    'memcached' => [
        'host' => '/var/run/memcached/memcached.sock', // Memcached sock
        'port' => 0 // Memcached port
    ],
    // You'll also need to uncomment the Redis throttler in index.php
    // If you want to enable this
    'redis' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379
    ],
    // Throttler config
    'throttler' => [
        'allowed_requests' => 700, // 700 allowed requests
        'minutes' => 3, // In 3 minutes
        'prefix' => 'c' // Cache key prefix
    ],
    // Throttler whitelist
    'throttler-whitelist' => [
        '1.2.3.4' => 0, // Local IP
        '127.0.0.1' => 0 // Local IP
    ],
    // Throttling policy
    'throttling-policy' => [
        'ban_time' => 60, // If exceed, ban for 60 minutes
        'cloudflare' => [
            'enabled' => false, // Is CloudFlare enabled?
            'email' => '',
            'auth_key' => '',
            'zone_id' => '',
            'mode' => 'block' // The action to apply if the IP get's banned
        ]
    ]
];
<?php

return [
    'name' => 'API 3 - GitHub, DEMO',
    'url' => 'images.weserv.nl',
    'exampleImage' => 'rbx.weserv.nl/lichtenstein.jpg',
    'exampleTransparentImage' => 'rbx.weserv.nl/transparency_demo.png',
    'exampleSmartcropImage' => 'ssl:upload.wikimedia.org/wikipedia/commons/4/45/Equus_quagga_burchellii_-_Etosha%2C_2014.jpg',
    // Client options
    'client' => [
        // User agent for this client
        'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/7.0; +http://images.weserv.nl/)',
        // Float describing the number of seconds to wait while trying to connect to a server.
        // Use 0 to wait indefinitely.
        'connect_timeout' => 5,
        // Float describing the timeout of the request in seconds. Use 0 to wait indefinitely.
        'timeout' => 10,
        // Integer describing the max image size to receive (in bytes). Use 0 for no limits.
        'max_image_size' => 0,
        // Integer describing the maximum number of allowed redirects.
        'max_redirects' => 10,
        // Allowed mime types. Use empty array to allow all mime types
        'allowed_mime_types' => [
            /*'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',*/
        ]
    ],
    // Specific Guzzle options
    'guzzle' => [
        'proxy' => [
            'http' => null,
            'https' => null,
            'no' => [],
        ],
        'curl' => [
            //CURLOPT_SSL_VERIFYPEER => false,
            //CURLOPT_SSL_VERIFYHOST => false
        ]
    ],
    // Memcached throttler
    'memcached' => [
        'host' => '/var/run/memcached/memcached.sock', // Memcached sock
        'port' => 0 // Memcached port
    ],
    // Redis throttler
    'redis' => [
        'scheme' => 'tcp',
        'host' => 'redis',
        'port' => 6379
    ],
    // Throttler config
    'throttler' => [
        'allowed_requests' => 700, // 700 allowed requests
        'minutes' => 3, // In 3 minutes
        'prefix' => 'c', // Cache key prefix,
        'driver' => 'redis' // Throttler driver; defaulting to redis
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
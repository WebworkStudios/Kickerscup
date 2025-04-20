<?php

declare(strict_types=1);

return [
    /**
     * Standard-Queue-Treiber
     * Mögliche Werte: 'redis', 'database'
     */
    'default' => env('QUEUE_DRIVER', 'redis'),

    /**
     * Konfigurationen für verschiedene Queue-Treiber
     */
    'drivers' => [
        'redis' => [
            'connection' => 'queue',
            'prefix' => env('QUEUE_REDIS_PREFIX', 'football_manager:queue:'),
        ],

        'database' => [
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90, // Sekunden
        ]
    ],

    /**
     * Queue-Worker-Konfiguration
     */
    'worker' => [
        'sleep' => 3, // Sekunden zwischen Durchläufen
        'max_tries' => 3, // Maximale Ausführungsversuche pro Job
        'timeout' => 60, // Timeout pro Job in Sekunden
    ]
];
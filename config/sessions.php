
<?php

declare(strict_types=1);

/**
 * Konfiguration für Sessions
 */
return [
    /**
     * Session-Grundeinstellungen
     */
    'name' => 'SECURE_SESSION',
    'lifetime' => 7200, // 2 Stunden
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',

    /**
     * Inaktivitäts-Timeout in Sekunden
     * Nach dieser Zeit ohne Aktivität wird die Session als abgelaufen betrachtet
     */
    'idle_timeout' => 3600, // 1 Stunde

    /**
     * Session-Handler-Einstellungen
     * 'file': Standardmäßige dateibasierte Sessions
     * 'redis': Redis-basierte Sessions für bessere Skalierbarkeit
     */
    'handler' => 'redis', // 'file' oder 'redis'

    /**
     * Garbage Collection für dateibasierte Sessions
     * Nur relevant wenn handler = 'file'
     */
    'gc' => [
        'maxlifetime' => 1440, // 24 Minuten
        'probability' => 1     // 1% Wahrscheinlichkeit, dass GC ausgeführt wird
    ],

    /**
     * Redis-Einstellungen für Sessions
     * Nur relevant wenn handler = 'redis'
     */
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int)env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int)env('REDIS_DB', 0),       // Standard-Redis-DB
        'prefix' => 'session:',                      // Präfix für Session-Keys
        
        /**
         * Verbindungsoptionen
         */
        'options' => [
            'connect_timeout' => 2.5,                // Timeout in Sekunden
            'read_timeout' => 2.5,                   // Timeout in Sekunden
            'retry_interval' => 100,                 // in Millisekunden
            'tcp_keepalive' => true,                 // TCP Keepalive aktivieren
        ],
        
        /**
         * Sentinel-Konfiguration für Hochverfügbarkeit
         * Deaktiviert standardmäßig
         */
        'sentinel' => [
            'enabled' => false,
            'master' => 'mymaster',                  // Name des Master-Knotens
            'nodes' => [                             // Liste der Sentinel-Knoten
                // ['host' => '127.0.0.1', 'port' => 26379],
                // ['host' => '127.0.0.1', 'port' => 26380],
            ],
        ],
        
        /**
         * Cluster-Konfiguration für horizontale Skalierung
         * Deaktiviert standardmäßig
         */
        'cluster' => [
            'enabled' => false,
            'nodes' => [
                // ['host' => '127.0.0.1', 'port' => 6379],
                // ['host' => '127.0.0.1', 'port' => 6380],
            ],
        ],
    ],
    
    /**
     * Session-Fingerprinting für erhöhte Sicherheit
     */
    'fingerprinting' => [
        'enabled' => true,
        // Zusätzliche Daten, die in den Fingerprint einfließen sollen
        'additional_data' => [
            // 'app_version' => '1.0',
        ],
    ],
    
    /**
     * Flash-Message-Konfiguration
     */
    'flash' => [
        'key' => '_flash',
    ],
];
<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use InvalidArgumentException;

class SessionConfiguration
{
    public string $name {
        get {
            return $this->_name;
        }
        set {
            if (empty($value)) {
                throw new InvalidArgumentException('Session name cannot be empty');
            }
            $this->_name = $value;
        }
    }
    public int $lifetime {
        get {
            return $this->_lifetime;
        }
        set {
            if ($value < 0) {
                throw new InvalidArgumentException('Lifetime cannot be negative');
            }
            $this->_lifetime = $value;
        }
    }
    private string $_name = 'app_session';
    private int $_lifetime = 86400;

    /**
     * @param int $absoluteLifetime
     * @param string $path Der Pfad für das Session-Cookie
     * @param string|null $domain Die Domain für das Session-Cookie (null für aktuelle Domain)
     * @param bool $secure Ob das Cookie nur über HTTPS gesendet werden soll
     * @param bool $httpOnly Ob das Cookie nur über HTTP zugänglich sein soll (nicht über JavaScript)
     * @param string $sameSite SameSite-Einstellung für das Cookie ('Lax', 'Strict', 'None')
     * @param int $gcProbability Garbage Collection-Wahrscheinlichkeit (0-100)
     * @param int $gcDivisor Garbage Collection-Divisor
     * @param int $gcMaxLifetime Maximale Lebensdauer für inaktive Sessions in Sekunden
     * @param int $idleTimeout Timeout für inaktive Sessions in Sekunden (0 = deaktiviert)
     * @param bool $strictIpCheck
     * @param bool $fingerprintCheck Ob die Session-Fingerprint-ÜÜberprüfung aktiviert sein soll
     * @param int $regenerateIdInterval Zeit in Sekunden, nach der die Session-ID automatisch regeneriert wird (0 = deaktiviert)
     * @param string $storeType Der zu verwendende Session-Store ('default', 'redis')
     * @param array $storeConfig Konfiguration für den Session-Store
     */
    public function __construct(
        public int     $absoluteLifetime = 2592000, // 30 Tage in Sekunden
        public string  $path = '/',
        public ?string $domain = null,
        public bool    $secure = true,           // HTTPS empfohlen
        public bool    $httpOnly = true,
        public string  $sameSite = 'Lax',      // Lax als Standard (guter Kompromiss)
        public int     $gcProbability = 1,        // Garbage Collection (1%)
        public int     $gcDivisor = 100,
        public int     $gcMaxLifetime = 7200,     // 2 Stunden
        public int     $idleTimeout = 3600,       // 1 Stunde
        public bool    $strictIpCheck = false,    // IP-Bereichsüberprüfung standardmäßig deaktiviert
        public bool    $fingerprintCheck = true,
        public int     $regenerateIdInterval = 1800, // 30 Minuten
        public string  $storeType = 'default',    // 'default', 'redis'
        public array   $storeConfig = [
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'timeout' => 1.0,
                'prefix' => 'sess:',
                'auth' => null,
                'database' => 0,
            ]
        ]
    )
    {
    }
}
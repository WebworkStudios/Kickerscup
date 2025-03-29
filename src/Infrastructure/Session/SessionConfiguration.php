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

    public int $absoluteLifetime {
        get {
            return $this->_absoluteLifetime;
        }
        set {
            if ($value < 0) {
                throw new InvalidArgumentException('Absolute lifetime cannot be negative');
            }
            $this->_absoluteLifetime = $value;
        }
    }

    public int $idleTimeout {
        get {
            return $this->_idleTimeout;
        }
        set {
            if ($value < 0) {
                throw new InvalidArgumentException('Idle timeout cannot be negative');
            }
            $this->_idleTimeout = $value;
        }
    }

    public int $regenerateIdInterval {
        get {
            return $this->_regenerateIdInterval;
        }
        set {
            if ($value < 0) {
                throw new InvalidArgumentException('Regenerate ID interval cannot be negative');
            }
            $this->_regenerateIdInterval = $value;
        }
    }

    private string $_name = 'app_session';
    private int $_lifetime = 86400;
    private int $_absoluteLifetime = 2592000; // 30 Tage in Sekunden
    private int $_idleTimeout = 3600; // 1 Stunde
    private int $_regenerateIdInterval = 1800; // 30 Minuten

    /**
     * @param string $path Der Pfad für das Session-Cookie
     * @param string|null $domain Die Domain für das Session-Cookie (null für aktuelle Domain)
     * @param bool $secure Ob das Cookie nur über HTTPS gesendet werden soll
     * @param bool $httpOnly Ob das Cookie nur über HTTP zugänglich sein soll (nicht über JavaScript)
     * @param string $sameSite SameSite-Einstellung für das Cookie ('Lax', 'Strict', 'None')
     * @param int $gcProbability Garbage Collection-Wahrscheinlichkeit (0-100)
     * @param int $gcDivisor Garbage Collection-Divisor
     * @param int $gcMaxLifetime Maximale Lebensdauer für inaktive Sessions in Sekunden
     * @param bool $strictIpCheck
     * @param bool $fingerprintCheck Ob die Session-Fingerprint-Ueberprüfung aktiviert sein soll
     * @param string $storeType Der zu verwendende Session-Store ('default', 'redis')
     * @param array $storeConfig Konfiguration für den Session-Store
     */
    public function __construct(
        public string  $path = '/',
        public ?string $domain = null,
        public bool    $secure = false,        // Geändert zu false für Entwicklung/Testing
        public bool    $httpOnly = true,
        public string  $sameSite = 'Lax',
        public int     $gcProbability = 1,
        public int     $gcDivisor = 100,
        public int     $gcMaxLifetime = 7200,
        public bool    $strictIpCheck = false,
        public bool    $fingerprintCheck = true,
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
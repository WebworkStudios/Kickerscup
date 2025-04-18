<?php

declare(strict_types=1);

namespace App\Core\Security;

use Redis;
use Exception;

/**
 * Session Management Klasse
 */
class Session
{
    /**
     * Session Konfiguration
     *
     * @var array<string, mixed>
     */
    private array $config;
    
    /**
     * Redis-Instanz
     */
    private ?Redis $redis = null;
    
    /**
     * Konstruktor
     * 
     * @param array<string, mixed> $config Optional. Konfiguration als Array
     */
    public function __construct(array $config = [])
    {
        // Lade die Standardkonfiguration aus der Datei
        $defaultConfig = [];
        $configFile = dirname(__DIR__, 3) . '/config/sessions.php';
        
        if (file_exists($configFile)) {
            $defaultConfig = require $configFile;
        }
        
        // Überschreibe Standardwerte mit übergebenen Werten
        $this->config = array_merge($defaultConfig, $config);
    }

    /**
     * Initialisiert die Session mit sicheren Einstellungen
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_lifetime', (string)$this->config['lifetime']);
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $this->config['httponly'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['samesite']);

        session_name($this->config['name']);
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite']
        ]);
        
        // Session-Handler einrichten
        $handler = $this->config['handler'] ?? 'file';
        
        if ($handler === 'redis') {
            $this->setupRedisHandler();
        } else {
            $this->setupFileHandler();
        }

        if (!session_start()) {
            throw new \RuntimeException('Session konnte nicht gestartet werden');
        }
    }
    
    /**
     * Richtet Redis als Session-Handler ein
     */
    private function setupRedisHandler(): void
    {
        try {
            $redis = new Redis();
            $redisConfig = $this->config['redis'] ?? [];
            
            // Sentinel-Modus für Hochverfügbarkeit
            if (!empty($redisConfig['sentinel']['enabled']) && $redisConfig['sentinel']['enabled']) {
                $this->setupRedisSentinel($redis, $redisConfig);
            } 
            // Cluster-Modus für horizontale Skalierung
            elseif (!empty($redisConfig['cluster']['enabled']) && $redisConfig['cluster']['enabled']) {
                $this->setupRedisCluster($redis, $redisConfig);
            } 
            // Standardmäßige Verbindung
            else {
                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                
                $options = $redisConfig['options'] ?? [];
                $timeout = $options['connect_timeout'] ?? 2.5;
                $retryInterval = $options['retry_interval'] ?? 100;
                
                if (!$redis->connect($host, $port, $timeout, null, $retryInterval)) {
                    throw new \RuntimeException("Verbindung zu Redis ($host:$port) konnte nicht hergestellt werden");
                }
                
                // Weitere Verbindungsoptionen setzen
                if (!empty($options['read_timeout'])) {
                    $redis->setOption(Redis::OPT_READ_TIMEOUT, $options['read_timeout']);
                }
                
                if (!empty($options['tcp_keepalive'])) {
                    $redis->setOption(Redis::OPT_TCP_KEEPALIVE, $options['tcp_keepalive']);
                }
            }
            
            // Authentifizierung
            $password = $redisConfig['password'] ?? null;
            if ($password !== null) {
                $redis->auth($password);
            }
            
            // Datenbank auswählen
            $database = $redisConfig['database'] ?? 0;
            if ($database > 0) {
                $redis->select($database);
            }
            
            $this->redis = $redis;
            
            $prefix = $redisConfig['prefix'] ?? 'session:';
            $lifetime = $this->config['lifetime'] ?? 7200;
            
            $handler = new RedisSessionHandler(
                $redis, 
                $lifetime, 
                $prefix
            );
            
            session_set_save_handler($handler, true);
        } catch (Exception $e) {
            // Fallback zur dateibasierten Session, wenn Redis nicht verfügbar ist
            error_log("Redis-Session-Handler konnte nicht initialisiert werden: " . $e->getMessage());
            $this->setupFileHandler();
        }
    }
    
    /**
     * Richtet Redis Sentinel für Hochverfügbarkeit ein
     * 
     * @param Redis $redis Redis-Instance
     * @param array<string, mixed> $redisConfig Redis-Konfiguration
     */
    private function setupRedisSentinel(Redis $redis, array $redisConfig): void
    {
        $sentinelConfig = $redisConfig['sentinel'] ?? [];
        $master = $sentinelConfig['master'] ?? 'mymaster';
        $nodes = $sentinelConfig['nodes'] ?? [];
        
        if (empty($nodes)) {
            throw new \RuntimeException("Redis Sentinel ist aktiviert, aber keine Knoten konfiguriert");
        }
        
        // Versuche, Verbindung über Sentinel herzustellen
        $masterInfo = null;
        
        foreach ($nodes as $node) {
            $sentinelHost = $node['host'] ?? '127.0.0.1';
            $sentinelPort = $node['port'] ?? 26379;
            
            if ($redis->connect($sentinelHost, $sentinelPort)) {
                $masterInfo = $redis->rawCommand('SENTINEL', 'get-master-addr-by-name', $master);
                if ($masterInfo) {
                    break;
                }
            }
        }
        
        if (!$masterInfo || count($masterInfo) < 2) {
            throw new \RuntimeException("Redis Sentinel konnte keinen Master-Knoten für '$master' finden");
        }
        
        // Verbindung zum Master herstellen
        $redis->close();
        if (!$redis->connect($masterInfo[0], (int)$masterInfo[1])) {
            throw new \RuntimeException("Verbindung zum Redis Master konnte nicht hergestellt werden");
        }
    }
    
    /**
     * Richtet Redis Cluster für horizontale Skalierung ein
     * 
     * @param Redis $redis Redis-Instance
     * @param array<string, mixed> $redisConfig Redis-Konfiguration
     */
    private function setupRedisCluster(Redis $redis, array $redisConfig): void
    {
        $clusterConfig = $redisConfig['cluster'] ?? [];
        $nodes = $clusterConfig['nodes'] ?? [];
        
        if (empty($nodes)) {
            throw new \RuntimeException("Redis Cluster ist aktiviert, aber keine Knoten konfiguriert");
        }
        
        $seedNodes = [];
        foreach ($nodes as $node) {
            $host = $node['host'] ?? '127.0.0.1';
            $port = $node['port'] ?? 6379;
            $seedNodes[] = "$host:$port";
        }
        
        if (!method_exists($redis, 'cluster')) {
            throw new \RuntimeException("Redis Cluster wird von der installierten Redis-Extension nicht unterstützt");
        }
        
        $redis->cluster('masters', $seedNodes);
    }
    
    /**
     * Richtet den Standard-Datei-Session-Handler ein
     */
    private function setupFileHandler(): void
    {
        $gcConfig = $this->config['gc'] ?? [];
        $maxlifetime = $gcConfig['maxlifetime'] ?? 1440;
        $probability = $gcConfig['probability'] ?? 1;
        
        ini_set('session.gc_maxlifetime', (string)$maxlifetime);
        ini_set('session.gc_probability', (string)$probability);
        ini_set('session.gc_divisor', '100');
    }

    /**
     * Generiert eine neue Session-ID
     *
     * @param bool $deleteOldSession Alte Session löschen
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Zerstört die aktuelle Session
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax'
                ]
            );
        }

        session_destroy();
    }

    /**
     * Setzt einen Wert in der Session
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Gibt einen Wert aus der Session zurück
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert
     * @return mixed Wert
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Entfernt einen Wert aus der Session
     *
     * @param string $key Schlüssel
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Prüft ob ein Wert in der Session existiert
     *
     * @param string $key Schlüssel
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Setzt die Session Konfiguration
     *
     * @param array<string, mixed> $config Konfiguration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Prüft, ob die aktuelle Session abgelaufen ist
     * 
     * @return bool True wenn Session abgelaufen ist
     */
    public function isExpired(): bool
    {
        $maxIdleTime = $this->config['idle_timeout'] ?? 3600;
        
        if (!$this->has('_last_activity')) {
            $this->set('_last_activity', time());
            return false;
        }

        $lastActivity = $this->get('_last_activity');
        if ((time() - $lastActivity) > $maxIdleTime) {
            return true;
        }

        $this->set('_last_activity', time());
        return false;
    }

    /**
     * Aktualisiert den Zeitstempel der letzten Aktivität
     */
    public function updateActivity(): void
    {
        $this->set('_last_activity', time());
    }

    /**
     * Setzt eine Flash-Message, die nur bei der nächsten Anfrage verfügbar ist
     * 
     * @param string $key Schlüssel
     * @param mixed $value Wert
     */
    public function setFlash(string $key, mixed $value): void
    {
        $flashKey = $this->config['flash']['key'] ?? '_flash';
        $flash = $this->get($flashKey, []);
        $flash[$key] = $value;
        $this->set($flashKey, $flash);
    }

    /**
     * Holt eine Flash-Message und löscht sie anschließend
     * 
     * @param string $key Schlüssel
     * @param mixed $default Standardwert
     * @return mixed Wert
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashKey = $this->config['flash']['key'] ?? '_flash';
        $flash = $this->get($flashKey, []);
        $value = $flash[$key] ?? $default;
        unset($flash[$key]);
        $this->set($flashKey, $flash);
        return $value;
    }

    /**
     * Prüft, ob eine Flash-Message existiert
     * 
     * @param string $key Schlüssel
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        $flashKey = $this->config['flash']['key'] ?? '_flash';
        $flash = $this->get($flashKey, []);
        return isset($flash[$key]);
    }
    
    /**
     * Setzt den Fingerprint für die aktuelle Session
     */
    public function setFingerprint(): void
    {
        if (empty($this->config['fingerprinting']['enabled'])) {
            return;
        }
        
        $data = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $additionalData = $this->config['fingerprinting']['additional_data'] ?? [];
        $data = array_merge($data, $additionalData);
        
        $this->set('_fingerprint', hash('sha256', json_encode($data)));
    }

    /**
     * Überprüft, ob der aktuelle Fingerprint mit dem gespeicherten übereinstimmt
     * 
     * @return bool
     */
    public function validateFingerprint(): bool
    {
        if (empty($this->config['fingerprinting']['enabled'])) {
            return true;
        }
        
        if (!$this->has('_fingerprint')) {
            $this->setFingerprint();
            return true;
        }
        
        $data = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $additionalData = $this->config['fingerprinting']['additional_data'] ?? [];
        $data = array_merge($data, $additionalData);
        
        $currentFingerprint = hash('sha256', json_encode($data));
        
        return hash_equals($this->get('_fingerprint'), $currentFingerprint);
    }

    /**
     * Gibt die Redis-Instanz zurück, falls verfügbar
     *
     * @return Redis|null
     */
    public function getRedis(): ?Redis
    {
        return $this->redis;
    }
}
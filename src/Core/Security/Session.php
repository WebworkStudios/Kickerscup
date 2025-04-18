<?php

declare(strict_types=1);

namespace App\Core\Security;

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
    private array $config = [
        'name' => 'SECURE_SESSION',
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

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

        if (!session_start()) {
            throw new \RuntimeException('Session konnte nicht gestartet werden');
        }
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
     * @param int $maxIdleTime Maximale Inaktivitätszeit in Sekunden
     * @return bool True wenn Session abgelaufen ist
     */
    public function isExpired(int $maxIdleTime = 3600): bool
    {
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
        $flash = $this->get('_flash', []);
        $flash[$key] = $value;
        $this->set('_flash', $flash);
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
        $flash = $this->get('_flash', []);
        $value = $flash[$key] ?? $default;
        unset($flash[$key]);
        $this->set('_flash', $flash);
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
        $flash = $this->get('_flash', []);
        return isset($flash[$key]);
    }
    
    
    /**
     * Setzt den Fingerprint für die aktuelle Session
     * 
     * @param array<string, string> $additionalData Zusätzliche Daten für den Fingerprint
     */
    public function setFingerprint(array $additionalData = []): void
    {
        $data = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $data = array_merge($data, $additionalData);
        $this->set('_fingerprint', hash('sha256', json_encode($data)));
    }

    /**
     * Überprüft, ob der aktuelle Fingerprint mit dem gespeicherten übereinstimmt
     * 
     * @param array<string, string> $additionalData Zusätzliche Daten für den Fingerprint
     * @return bool
     */
    public function validateFingerprint(array $additionalData = []): bool
    {
        if (!$this->has('_fingerprint')) {
            $this->setFingerprint($additionalData);
            return true;
        }
        
        $data = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $data = array_merge($data, $additionalData);
        $currentFingerprint = hash('sha256', json_encode($data));
        
        return hash_equals($this->get('_fingerprint'), $currentFingerprint);
    }

    /**
     * Konfiguriert die Session-Garbage-Collection
     * 
     * @param int $maxLifetime Maximale Lebensdauer in Sekunden
     * @param int $probability Wahrscheinlichkeit (1 bedeutet 1%)
     */
    public function configureGarbageCollection(int $maxLifetime = 1440, int $probability = 1): void
    {
        ini_set('session.gc_maxlifetime', (string)$maxLifetime);
        ini_set('session.gc_probability', (string)$probability);
        ini_set('session.gc_divisor', '100');
    }
}
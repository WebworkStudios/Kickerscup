<?php

declare(strict_types=1);

namespace App\Core\Security;

use Redis;
use Exception;

/**
 * API-optimierte Session-Klasse
 *
 * Diese vereinfachte Session-Implementierung ist für API-Backends optimiert
 * und bietet bessere Unterstützung für zustandslose (stateless) Architekturen.
 */
class Session implements SessionInterface
{
    /**
     * Session-Konfiguration
     */
    private array $config;

    /**
     * Redis-Instanz für verteilte Sessions
     */
    private ?Redis $redis = null;

    /**
     * Session-ID
     */
    private ?string $sessionId = null;

    /**
     * Aktive Session-Daten
     */
    private array $data = [];

    /**
     * Flag, ob Session geändert wurde
     */
    private bool $changed = false;

    /**
     * Konstruktor
     *
     * @param array $config Session-Konfiguration
     */
    public function __construct(array $config = [])
    {
        // Standard-Konfiguration
        $defaultConfig = [];
        $configFile = dirname(__DIR__, 3) . '/config/sessions.php';

        if (file_exists($configFile)) {
            $defaultConfig = require $configFile;
        }

        // Mit benutzerdefinierten Einstellungen überschreiben
        $this->config = array_merge([
            'driver' => 'file', // 'file', 'redis', 'array'
            'lifetime' => 7200,
            'path' => '/api',
            'domain' => null,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
            'cookie' => 'api_session',
            'id_length' => 64,
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => null,
                'database' => 0,
                'prefix' => 'api_session:'
            ],
            'storage_path' => null
        ], $defaultConfig, $config);

        // Speicherpfad für dateibasierte Sessions setzen
        if ($this->config['storage_path'] === null) {
            $this->config['storage_path'] = dirname(__DIR__, 3) . '/storage/sessions';
        }
    }

    /**
     * Startet eine Session
     */
    public function start(): void
    {
        if ($this->sessionId !== null) {
            return;
        }

        // Session-ID aus Cookie oder API-Header extrahieren
        $this->sessionId = $this->getSessionIdFromRequest();

        if ($this->sessionId === null) {
            // Neue Session erstellen
            $this->sessionId = $this->generateSessionId();
            $this->data = [];
        } else {
            // Daten laden
            $this->data = $this->loadSessionData($this->sessionId);
        }

        // Sicherstellen, dass alle Basis-Strukturen vorhanden sind
        $this->data['_metadata'] = $this->data['_metadata'] ?? [
            'created_at' => time(),
            'last_activity' => time()
        ];

        // Activity aktualisieren
        $this->updateActivity();
    }

    /**
     * Aktualisiert den Zeitstempel der letzten Aktivität
     */
    public function updateActivity(): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $this->data['_metadata']['last_activity'] = time();
        $this->changed = true;
    }

    /**
     * Generiert eine neue Session-ID
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        if ($this->sessionId === null) {
            $this->start();
            return;
        }

        $oldId = $this->sessionId;
        $oldData = $this->data;

        // Neue ID generieren
        $this->sessionId = $this->generateSessionId();

        if ($deleteOldSession) {
            $this->deleteSession($oldId);
        }

        // Daten mit neuer ID speichern
        $this->saveSessionData();

        // Session-Cookie aktualisieren
        $this->setSessionCookie();
    }

    /**
     * Zerstört die aktuelle Session
     */
    public function destroy(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $this->deleteSession($this->sessionId);
        $this->sessionId = null;
        $this->data = [];

        // Cookie löschen
        $this->clearSessionCookie();
    }

    /**
     * Setzt einen Wert in der Session
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $this->data[$key] = $value;
        $this->changed = true;
    }

    /**
     * Holt einen Wert aus der Session
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert
     * @return mixed Wert
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Entfernt einen Wert aus der Session
     *
     * @param string $key Schlüssel
     */
    public function remove(string $key): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
            $this->changed = true;
        }
    }

    /**
     * Prüft, ob ein Schlüssel in der Session existiert
     *
     * @param string $key Schlüssel
     * @return bool
     */
    public function has(string $key): bool
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        return isset($this->data[$key]);
    }

    /**
     * Prüft, ob die Session abgelaufen ist
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $lastActivity = $this->data['_metadata']['last_activity'] ?? 0;
        $timeout = $this->config['lifetime'] ?? 7200;

        return (time() - $lastActivity) > $timeout;
    }

    /**
     * Setzt eine Flash-Message
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     */
    public function setFlash(string $key, mixed $value): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $flash = $this->data['_flash'] ?? [];
        $flash[$key] = $value;

        $this->data['_flash'] = $flash;
        $this->changed = true;
    }

    /**
     * Holt eine Flash-Message und löscht sie
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert
     * @return mixed Wert
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $flash = $this->data['_flash'] ?? [];
        $value = $flash[$key] ?? $default;

        if (isset($flash[$key])) {
            unset($flash[$key]);
            $this->data['_flash'] = $flash;
            $this->changed = true;
        }

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
        if ($this->sessionId === null) {
            $this->start();
        }

        $flash = $this->data['_flash'] ?? [];
        return isset($flash[$key]);
    }

    /**
     * Implementiert Rate-Limiting
     *
     * @param string $key Identifier für Rate-Limit
     * @param int $maxAttempts Maximale Versuche
     * @param int $timeWindow Zeitfenster in Sekunden
     * @return bool
     */
    public function isRateLimited(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $rateLimitKey = "rate_limit_{$key}";
        $attempts = $this->get($rateLimitKey, []);
        $now = time();

        // Versuche innerhalb des Zeitfensters filtern
        $attempts = array_filter($attempts, fn($timestamp) => $timestamp > ($now - $timeWindow));

        // Limit prüfen
        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        // Aktuellen Zeitstempel hinzufügen
        $attempts[] = $now;
        $this->set($rateLimitKey, $attempts);

        return false;
    }

    /**
     * Sperrt die Session für konkurrierende Zugriffe
     *
     * @param int $timeout Timeout in Sekunden
     * @return bool
     */
    public function lock(int $timeout = 30): bool
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        // Bei Array-Driver: Keine Sperre notwendig
        if ($this->config['driver'] === 'array') {
            return true;
        }

        // Bei Redis-Driver: Distributed Lock
        if ($this->config['driver'] === 'redis') {
            return $this->acquireRedisLock($timeout);
        }

        // Bei File-Driver: Datei-Lock
        return $this->acquireFileLock($timeout);
    }

    /**
     * Hebt die Sperre der Session auf
     *
     * @return bool
     */
    public function unlock(): bool
    {
        if ($this->sessionId === null) {
            return false;
        }

        // Bei Array-Driver: Keine Sperre notwendig
        if ($this->config['driver'] === 'array') {
            return true;
        }

        // Bei Redis-Driver: Distributed Lock freigeben
        if ($this->config['driver'] === 'redis') {
            return $this->releaseRedisLock();
        }

        // Bei File-Driver: Datei-Lock freigeben
        return $this->releaseFileLock();
    }

    /**
     * Setzt einen verschlüsselten Wert
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     */
    public function setEncrypted(string $key, mixed $value): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        // Sicher serialisieren und verschlüsseln
        // In echten Implementierungen würde hier eine Verschlüsselungsfunktion verwendet
        $encrypted = base64_encode(serialize($value));

        $this->set("_encrypted_{$key}", $encrypted);
    }

    /**
     * Holt einen verschlüsselten Wert
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert
     * @return mixed
     */
    public function getEncrypted(string $key, mixed $default = null): mixed
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $encrypted = $this->get("_encrypted_{$key}");

        if ($encrypted === null) {
            return $default;
        }

        try {
            // Entschlüsseln und deserialisieren
            return unserialize(base64_decode($encrypted));
        } catch (Exception $e) {
            app_log('Fehler beim Entschlüsseln: ' . $e->getMessage(), [], 'error');
            return $default;
        }
    }

    /**
     * Speichert die Session-Daten
     */
    public function save(): void
    {
        if ($this->sessionId === null || !$this->changed) {
            return;
        }

        $this->saveSessionData();
        $this->changed = false;
    }

    /**
     * Gibt die Session-ID zurück
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Gibt alle Session-Daten zurück
     *
     * @return array
     */
    public function all(): array
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        return $this->data;
    }

    /**
     * Löscht alle Session-Daten
     */
    public function flush(): void
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $metadata = $this->data['_metadata'] ?? [
            'created_at' => time(),
            'last_activity' => time()
        ];

        $this->data = [
            '_metadata' => $metadata
        ];

        $this->changed = true;
    }

    /**
     * Erstellt einen Token für CSRF-Schutz oder API-Authentifizierung
     *
     * @return string
     */
    public function token(): string
    {
        if ($this->sessionId === null) {
            $this->start();
        }

        $token = $this->get('_token');

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            $this->set('_token', $token);
        }

        return $token;
    }

    // Private Hilfsmethoden

    /**
     * Generiert eine neue Session-ID
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes($this->config['id_length'] / 2));
    }

    /**
     * Lädt Session-Daten
     *
     * @param string $id Session-ID
     * @return array
     */
    private function loadSessionData(string $id): array
    {
        $driver = $this->config['driver'];

        if ($driver === 'array') {
            return [];
        }

        if ($driver === 'redis') {
            return $this->loadRedisSessionData($id);
        }

        // Default: File
        return $this->loadFileSessionData($id);
    }

    /**
     * Speichert Session-Daten
     *
     * @return bool
     */
    private function saveSessionData(): bool
    {
        if ($this->sessionId === null) {
            return false;
        }

        $driver = $this->config['driver'];

        if ($driver === 'array') {
            return true;
        }

        if ($driver === 'redis') {
            return $this->saveRedisSessionData();
        }

        // Default: File
        return $this->saveFileSessionData();
    }

    /**
     * Löscht eine Session
     *
     * @param string $id Session-ID
     * @return bool
     */
    private function deleteSession(string $id): bool
    {
        $driver = $this->config['driver'];

        if ($driver === 'array') {
            return true;
        }

        if ($driver === 'redis') {
            return $this->deleteRedisSession($id);
        }

        // Default: File
        return $this->deleteFileSession($id);
    }

    /**
     * Lädt Session-Daten aus Redis
     *
     * @param string $id Session-ID
     * @return array
     */
    private function loadRedisSessionData(string $id): array
    {
        $redis = $this->getRedisConnection();
        $key = $this->config['redis']['prefix'] . $id;

        $data = $redis->get($key);

        if ($data === false) {
            return [];
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Exception $e) {
            app_log('Fehler beim Dekodieren der Session-Daten: ' . $e->getMessage(), [], 'error');
            return [];
        }
    }

    /**
     * Speichert Session-Daten in Redis
     *
     * @return bool
     */
    private function saveRedisSessionData(): bool
    {
        $redis = $this->getRedisConnection();
        $key = $this->config['redis']['prefix'] . $this->sessionId;

        try {
            $encoded = json_encode($this->data, JSON_THROW_ON_ERROR);
            $ttl = $this->config['lifetime'];

            return $redis->setex($key, $ttl, $encoded);
        } catch (Exception $e) {
            app_log('Fehler beim Speichern der Session-Daten in Redis: ' . $e->getMessage(), [], 'error');
            return false;
        }
    }

    /**
     * Löscht eine Session aus Redis
     *
     * @param string $id Session-ID
     * @return bool
     */
    private function deleteRedisSession(string $id): bool
    {
        $redis = $this->getRedisConnection();
        $key = $this->config['redis']['prefix'] . $id;

        return (bool)$redis->del($key);
    }

    /**
     * Stellt eine Redis-Verbindung her
     *
     * @return Redis
     */
    private function getRedisConnection(): Redis
    {
        if ($this->redis === null) {
            $config = $this->config['redis'];

            $this->redis = new Redis();
            $this->redis->connect(
                $config['host'],
                $config['port'],
                2.0
            );

            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }

            if (!empty($config['database'])) {
                $this->redis->select($config['database']);
            }
        }

        return $this->redis;
    }

    /**
     * Lädt Session-Daten aus einer Datei
     *
     * @param string $id Session-ID
     * @return array
     */
    private function loadFileSessionData(string $id): array
    {
        $path = $this->getSessionFilePath($id);

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Exception $e) {
            app_log('Fehler beim Dekodieren der Session-Datei: ' . $e->getMessage(), [], 'error');
            return [];
        }
    }

    /**
     * Speichert Session-Daten in einer Datei
     *
     * @return bool
     */
    private function saveFileSessionData(): bool
    {
        $path = $this->getSessionFilePath($this->sessionId);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                app_log('Fehler beim Erstellen des Session-Verzeichnisses', [], 'error');
                return false;
            }
        }

        try {
            $encoded = json_encode($this->data, JSON_THROW_ON_ERROR);
            return file_put_contents($path, $encoded, LOCK_EX) !== false;
        } catch (Exception $e) {
            app_log('Fehler beim Speichern der Session-Datei: ' . $e->getMessage(), [], 'error');
            return false;
        }
    }

    /**
     * Löscht eine Session-Datei
     *
     * @param string $id Session-ID
     * @return bool
     */
    private function deleteFileSession(string $id): bool
    {
        $path = $this->getSessionFilePath($id);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    /**
     * Gibt den Pfad zur Session-Datei zurück
     *
     * @param string $id Session-ID
     * @return string
     */
    private function getSessionFilePath(string $id): string
    {
        $storagePath = $this->config['storage_path'];

        // Sessiondaten in verteilten Unterverzeichnissen speichern
        // für bessere Performance bei vielen Sessions
        $subDir = substr($id, 0, 2);

        return "$storagePath/$subDir/$id.json";
    }

    /**
     * Extrahiert die Session-ID aus dem Request
     *
     * @return string|null
     */
    private function getSessionIdFromRequest(): ?string
    {
        // Zuerst aus dem Cookie
        $cookieName = $this->config['cookie'];
        $id = $_COOKIE[$cookieName] ?? null;

        // Dann aus dem API-Header
        if ($id === null) {
            $headers = getallheaders();
            $id = $headers['X-Session-ID'] ?? null;
        }

        // Dann aus dem Authorization-Header (Bearer)
        if ($id === null) {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? '';

            if (preg_match('/^Session\s+([a-zA-Z0-9]+)$/i', $auth, $matches)) {
                $id = $matches[1];
            }
        }

        return $id;
    }

    /**
     * Setzt das Session-Cookie
     */
    private function setSessionCookie(): void
    {
        if (headers_sent() || $this->sessionId === null) {
            return;
        }

        $cookieName = $this->config['cookie'];
        $lifetime = $this->config['lifetime'];
        $path = $this->config['path'];
        $domain = $this->config['domain'];
        $secure = $this->config['secure'];
        $httpOnly = $this->config['httponly'];
        $sameSite = $this->config['samesite'];

        setcookie($cookieName, $this->sessionId, [
            'expires' => time() + $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ]);
    }

    /**
     * Löscht das Session-Cookie
     */
    private function clearSessionCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $cookieName = $this->config['cookie'];
        $path = $this->config['path'];
        $domain = $this->config['domain'];
        $secure = $this->config['secure'];
        $httpOnly = $this->config['httponly'];
        $sameSite = $this->config['samesite'];

        setcookie($cookieName, '', [
            'expires' => time() - 42000,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ]);
    }

    /**
     * Erwirbt eine Redis-Sperre
     *
     * @param int $timeout Timeout in Sekunden
     * @return bool
     */
    private function acquireRedisLock(int $timeout): bool
    {
        $redis = $this->getRedisConnection();
        $lockKey = "lock:{$this->sessionId}";
        $token = bin2hex(random_bytes(8));

        $this->set('_lock_token', $token);

        $startTime = microtime(true);
        $acquired = false;

        // Versuchen, die Sperre zu erhalten
        while (microtime(true) - $startTime < $timeout) {
            $acquired = $redis->set($lockKey, $token, ['NX', 'EX' => $timeout]);

            if ($acquired) {
                break;
            }

            // Kurze Pause
            usleep(10000); // 10ms
        }

        return (bool)$acquired;
    }

    /**
     * Gibt eine Redis-Sperre frei
     *
     * @return bool
     */
    private function releaseRedisLock(): bool
    {
        $redis = $this->getRedisConnection();
        $lockKey = "lock:{$this->sessionId}";
        $token = $this->get('_lock_token');

        if (!$token) {
            return false;
        }

        // Lua-Script für atomares Prüfen und Löschen
        $script = <<<LUA
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('del', KEYS[1])
        else
            return 0
        end
        LUA;

        $result = $redis->eval($script, [$lockKey, $token], 1);
        return (bool)$result;
    }

    /**
     * Erwirbt eine Datei-Sperre
     *
     * @param int $timeout Timeout in Sekunden
     * @return bool
     */
    private function acquireFileLock(int $timeout): bool
    {
        $lockFile = $this->getSessionFilePath($this->sessionId) . '.lock';
        $startTime = microtime(true);

        while (microtime(true) - $startTime < $timeout) {
            $fp = fopen($lockFile, 'w+');

            if ($fp === false) {
                return false;
            }

            if (flock($fp, LOCK_EX | LOCK_NB)) {
                $this->set('_lock_handle', $fp);
                return true;
            }

            fclose($fp);
            usleep(10000); // 10ms
        }

        return false;
    }

    /**
     * Gibt eine Datei-Sperre frei
     *
     * @return bool
     */
    private function releaseFileLock(): bool
    {
        $fp = $this->get('_lock_handle');

        if (!$fp) {
            return false;
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        $this->remove('_lock_handle');

        $lockFile = $this->getSessionFilePath($this->sessionId) . '.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        return true;
    }
}
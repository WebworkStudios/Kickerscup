<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\ErrorHandling\Contracts\ExceptionHandlerInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Logging\FileLogger;
use App\Infrastructure\Logging\LoggerConfiguration;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;
use App\Infrastructure\Session\Contracts\UserSessionStoreInterface;
use App\Infrastructure\Session\Store\DefaultSessionStore;
use RuntimeException;
use Throwable;

#[Injectable]
#[Singleton]
class Session implements SessionInterface
{
    /**
     * Konstante für Benutzer-Session-Metadaten
     */
    protected const string USER_SESSION_KEY = '_user_session';
    /**
     * Flag, ob die Session gestartet wurde
     */
    protected bool $started = false;

    /**
     * Konstruktor
     */
    public function __construct(
        protected FlashMessageProvider  $flashProvider,
        protected SessionConfiguration  $config,
        protected SessionStoreInterface $store,
        protected ?LoggerInterface      $logger = null
    )
    {
        if (!$store instanceof DefaultSessionStore) {
            session_set_save_handler(
                [$store, 'open'],
                [$store, 'close'],
                [$store, 'read'],
                [$store, 'write'],
                [$store, 'destroy'],
                [$store, 'gc']
            );
        }

        // Logger initialisieren, wenn nicht vorhanden
        if ($this->logger === null) {
            try {
                // Versuchen, den Logger aus einfachem Service zu bekommen
                // Nicht über SessionInterface gehen, um Zirkularbezug zu vermeiden
                $this->logger = new FileLogger(new LoggerConfiguration());
            } catch (Throwable) {
                // Fehler ignorieren - arbeiten ohne Logger
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): static
    {
        if (!$this->started) {
            $this->start();
        }

        unset($_SESSION[$key]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        $this->configureSession();
        session_name($this->config->name);

        // Starte die Session
        $this->started = session_start();

        if ($this->started) {
            $this->logger?->debug('Session started', ['id' => $this->getId()]);

            // Initialisiere neue Session mit Zeitstempel und Fingerprint
            if (!$this->has('_created_at')) {
                $this->set('_created_at', time());
                $this->saveFingerprint();
                return true;
            }

            // Prüfe absolute Lebensdauer
            if ($this->hasAbsoluteLifetimeExpired()) {
                $this->logger?->debug('Session lifetime expired, creating new session', ['id' => $this->getId()]);
                $this->destroy();
                session_start();
                $this->started = true;
                $this->set('_created_at', time());
                $this->saveFingerprint();
                return true;
            }

            // Wichtig: Fingerprint-Validierung nur für etablierte Sessions durchführen,
            // NICHT für die erste Anfrage einer neuen Session
            if ($this->has('_fingerprint') && !$this->validateFingerprint()) {
                $this->logger?->warning('Fingerprint validation failed', [
                    'id' => $this->getId(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                $this->destroy();
                session_start();
                $this->started = true;
                $this->set('_created_at', time());
                $this->saveFingerprint();
                return true;
            }

            // Aktualisiere letzte Aktivität und prüfe ob eine Rotation nötig ist
            $this->checkActivity();
            $this->rotateId(false); // keine erzwungene Rotation

            return true;
        }

        $this->logger?->error('Failed to start session');
        return false;
    }

    /**
     * Konfiguriert die PHP-Session mit den Einstellungen aus der Konfiguration
     */
    protected function configureSession(): void
    {
        // Verwende konstante Cookie-Parameter für konsistentes Verhalten
        $lifetime = $this->config->lifetime > 0 ? $this->config->lifetime : 0;

        // Setze Cookie-Parameter mit korrekten Werten für die Konsistenz
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $this->config->path,
            'domain' => $this->config->domain,
            'secure' => $this->config->secure,
            'httponly' => $this->config->httpOnly,
            'samesite' => $this->config->sameSite,
        ]);

        // Garbage Collection konfigurieren
        ini_set('session.gc_probability', (string)$this->config->gcProbability);
        ini_set('session.gc_divisor', (string)$this->config->gcDivisor);
        ini_set('session.gc_maxlifetime', (string)$this->config->gcMaxLifetime);

        // Wichtig: Stelle sicher, dass die Session-Cookies verwendet werden
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        // Verhindere SID-Übertragung in URLs
        ini_set('session.use_trans_sid', '0');
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        if (!$this->started) {
            $this->start();
        }

        return session_id() ?: null;
    }

    protected function hasAbsoluteLifetimeExpired(): bool
    {
        $createdAt = $this->get('_created_at');
        if (!$createdAt) {
            return false; // Keine Erstellungszeit, kann nicht prüfen
        }

        $now = time();
        $maxAge = $this->config->absoluteLifetime;

        return ($now - $createdAt) > $maxAge;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            $this->start();
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): bool
    {
        if (!$this->started) {
            $this->start();
        }

        // Lösche Session-Daten
        $_SESSION = [];

        // Lösche Session-Cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Zerstöre die Session
        $result = session_destroy();
        $this->started = false;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): static
    {
        if (!$this->started) {
            $this->start();
        }

        $_SESSION[$key] = $value;

        return $this;
    }



    public function isValid(): bool
    {
        // Schnelle Prüfungen zuerst
        if (!$this->started) {
            return false;
        }

        // Neue Sessions sind immer gültig - falls keine _created_at vorhanden ist
        if (!$this->has('_created_at')) {
            // Für neue Sessions Fingerprint setzen
            $this->saveFingerprint();
            $this->set('_created_at', time());
            return true;
        }

        // Absolute Lebensdauer prüfen
        if ($this->hasAbsoluteLifetimeExpired()) {
            return false;
        }

        // Fingerprint-Prüfung nur wenn aktiviert
        if ($this->config->fingerprintCheck && !$this->validateFingerprint()) {
            return false;
        }

        // Prüfe den Idle-Timeout
        if (!$this->checkActivity()) {
            return false;
        }


        // Benutzer-Session-Prüfungen nur wenn vorhanden
        if ($this->has(self::USER_SESSION_KEY)) {
            $sessionData = $this->get(self::USER_SESSION_KEY);

            // Prüfe, ob die gespeicherten Daten plausibel sind
            if (!isset($sessionData['user_id']) || !isset($sessionData['bound_at'])) {
                return false;
            }

            // Benutzer-Agent-Prüfung
            $currentUserAgent = $this->getUserAgent();
            $storedUserAgent = $sessionData['user_agent'] ?? null;

            if ($storedUserAgent !== null && $currentUserAgent !== null && $storedUserAgent !== $currentUserAgent) {
                return false;
            }

            // IP-Prüfung nur wenn konfiguriert
            if ($this->config->strictIpCheck && isset($sessionData['client_ip'])) {
                $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
                $storedIp = $sessionData['client_ip'];

                // IPv4-Vergleich
                $currentIpParts = explode('.', $currentIp);
                $storedIpParts = explode('.', $storedIp);

                if (count($currentIpParts) >= 2 && count($storedIpParts) >= 2) {
                    if ($currentIpParts[0] !== $storedIpParts[0] || $currentIpParts[1] !== $storedIpParts[1]) {
                        return false;
                    }
                } else {
                    // IPv6 oder nicht vergleichbar
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Überprüft die Fingerprint-Validität
     */
    public function validateFingerprint(): bool
    {
        // Wenn kein Fingerprint vorhanden ist, gib true zurück (neue Sessions sind gültig)
        if (!$this->has('_fingerprint')) {
            return true;
        }

        $storedFingerprint = $this->get('_fingerprint');
        $currentFingerprint = $this->generateFingerprint();

        return hash_equals($storedFingerprint, $currentFingerprint);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (!$this->started) {
            $this->start();
        }

        return isset($_SESSION[$key]);
    }

    /**
     * Generiert einen Fingerprint basierend auf Client-Informationen
     *
     * @return string Der generierte Fingerprint
     */
    protected function generateFingerprint(): string
    {
        // Sammle Client-Informationen
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipSegments = explode('.', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ipPrefix = count($ipSegments) >= 3 ? $ipSegments[0] . '.' . $ipSegments[1] . '.' . $ipSegments[2] : '';

        // Erstelle den Fingerprint (nur teilweise IP verwenden, um bei dynamischen IPs flexibel zu sein)
        return hash('sha256', $userAgent . $ipPrefix);
    }

    /**
     * {@inheritdoc}
     */
    public function checkActivity(): bool
    {
        if (!$this->started) {
            $this->start();
        }

        $now = time();
        $lastActivity = $this->getLastActivity();

        // Wenn keine letzte Aktivität vorhanden ist oder Idle-Timeout deaktiviert ist
        if ($lastActivity === null || $this->config->idleTimeout <= 0) {
            $this->set('last_activity', $now);
            return true;
        }

        // Prüfe, ob die Session abgelaufen ist
        if (($now - $lastActivity) > $this->config->idleTimeout) {
            return false;
        }

        // Aktualisiere die letzte Aktivität
        $this->set('last_activity', $now);
        return true;
    }

    /**
     * Gibt den Zeitpunkt der letzten Aktivität zurück
     *
     * @return int|null Der Zeitpunkt der letzten Aktivität oder null, wenn nicht gesetzt
     */
    public function getLastActivity(): ?int
    {
        if (!$this->started) {
            $this->start();
        }

        return $this->get('last_activity');
    }

    /**
     * {@inheritdoc}
     */
    public function getUserAgent(): ?string
    {
        if (!$this->started) {
            $this->start();
        }

        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function saveFingerprint(): void
    {
        if (!$this->started) {
            $this->start();
        }

        // Erstelle einen Fingerprint basierend auf Client-Informationen
        $fingerprint = $this->generateFingerprint();

        // Speichere den Fingerprint in der Session
        $_SESSION['_fingerprint'] = $fingerprint;
    }

    /**
     * {@inheritdoc}
     */
    public function rotateId(bool $force = false): bool
    {
        if (!$this->started) {
            $this->start();
        }

        // Wenn Rotation deaktiviert ist und nicht erzwungen wird
        if ($this->config->regenerateIdInterval <= 0 && !$force) {
            return false;
        }

        // Wenn,last_rotation nicht gesetzt ist oder das Intervall überschritten wurde
        $lastRotation = $this->get('_last_rotation', 0);
        $now = time();

        if ($force || !$lastRotation || ($now - $lastRotation) > $this->config->regenerateIdInterval) {
            // Verwende true als expliziten Wert, um die Warnung zu vermeiden
            $result = $this->regenerate();

            if ($result) {
                $this->set('_last_rotation', $now);
            }

            return $result;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (!$this->started) {
            $this->start();
        }

        return session_regenerate_id($deleteOldSession);
    }

    public function isStarted(): bool
    {
        return $this->started; // oder entsprechender Status
    }

    /**
     * {@inheritdoc}
     */
    public function flash(string $key, mixed $value): static
    {
        $this->flashProvider->getFlashMessage()->add($key, $value);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->flashProvider->getFlashMessage()->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function setId(string $id): static
    {
        if ($this->started) {
            throw new RuntimeException('Cannot change session ID after the session has started');
        }

        session_id($id);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->config->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): static
    {
        if ($this->started) {
            throw new RuntimeException('Cannot change session name after the session has started');
        }

        session_name($name);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): static
    {
        if (!$this->started) {
            $this->start();
        }

        $_SESSION = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        if (!$this->started) {
            $this->start();
        }

        return $_SESSION;
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        if (!$this->started) {
            try {
                $this->start();
            } catch (Throwable $e) {
                // ExceptionHandler verwenden, wenn verfügbar
                if (isset($this->container) && $this->container->has(ExceptionHandlerInterface::class)) {
                    $this->container->get(ExceptionHandlerInterface::class)->report($e, [
                        'context' => 'session_flush'
                    ]);
                }
                return false;
            }
        }

        // Aktualisiere den Zeitpunkt der letzten Aktivität
        $this->set('last_activity', time());

        // Schreibe die Session-Daten und beende sie
        return session_write_close();
    }

    /**
     * {@inheritdoc}
     */
    public function bindToUser(int|string $userId): static
    {
        if (!$this->started) {
            $this->start();
        }

        // Speichere die Benutzer-ID in der Session
        $this->set(self::USER_SESSION_KEY, [
            'user_id' => $userId,
            'bound_at' => time(),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $this->getUserAgent(),
        ]);

        // Regeneriere die Session-ID, um Session-Fixation zu verhindern
        $this->regenerate();

        return $this;
    }

    // Neue Methode zum Überprüfen der absoluten Lebensdauer

    /**
     * {@inheritdoc}
     */
    public function getBoundUserId(): int|string|null
    {
        if (!$this->started) {
            $this->start();
        }

        $sessionData = $this->get(self::USER_SESSION_KEY);
        return $sessionData['user_id'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateUserSessions(int|string $userId): bool
    {
        // Diese Methode benötigt eine Store-Implementation, die alle Sessions durchsuchen kann
        // für den Standard-PHP-Store müsste eine externe Tracking-Tabelle verwendet werden

        if ($this->store instanceof UserSessionStoreInterface) {
            return $this->store->invalidateUserSessions($userId);
        }

        // Wenn der aktuelle Session-Store nicht unterstützt wird, zumindest die aktuelle Session invalidieren
        if ($this->isBoundToUser($userId)) {
            return $this->destroy();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isBoundToUser(int|string $userId): bool
    {
        if (!$this->started) {
            $this->start();
        }

        $sessionData = $this->get(self::USER_SESSION_KEY);
        return $sessionData !== null && $sessionData['user_id'] == $userId;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserActiveSessions(int|string $userId): array
    {
        if ($this->store instanceof UserSessionStoreInterface) {
            return $this->store->getUserActiveSessions($userId);
        }

        // Fallback: Nur die aktuelle Session zurückgeben, wenn sie zum Benutzer gehört
        if ($this->isBoundToUser($userId)) {
            return [
                [
                    'id' => $this->getId(),
                    'created_at' => $this->get('_created_at'),
                    'last_activity' => $this->getLastActivity(),
                    'user_agent' => $this->getUserAgent(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'current' => true
                ]
            ];
        }

        return [];
    }

    private function isUserSessionValid(): bool
    {
        $sessionData = $this->get(self::USER_SESSION_KEY);

        // Prüfe, ob die gespeicherten Daten plausibel sind
        if (!isset($sessionData['user_id']) || !isset($sessionData['bound_at'])) {
            return false;
        }

        // Benutzer-Agent-Prüfung
        $currentUserAgent = $this->getUserAgent();
        $storedUserAgent = $sessionData['user_agent'] ?? null;

        if ($storedUserAgent !== null && $currentUserAgent !== null && $storedUserAgent !== $currentUserAgent) {
            return false;
        }

        // IP-Prüfung nur wenn konfiguriert
        if ($this->config->strictIpCheck && isset($sessionData['client_ip'])) {
            return $this->validateIpAddress($sessionData['client_ip']);
        }

        return true;
    }

    private function validateIpAddress(string $storedIp): bool
    {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';

        // IPv4-Vergleich
        $currentIpParts = explode('.', $currentIp);
        $storedIpParts = explode('.', $storedIp);

        if (count($currentIpParts) >= 2 && count($storedIpParts) >= 2) {
            return $currentIpParts[0] === $storedIpParts[0] &&
                $currentIpParts[1] === $storedIpParts[1];
        }

        // IPv6 oder nicht vergleichbar
        return false;
    }
}
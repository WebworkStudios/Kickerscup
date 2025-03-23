<?php


declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use RuntimeException;

#[Injectable]
#[Singleton]
class Session implements SessionInterface
{
    /**
     * Flag, ob die Session gestartet wurde
     */
    protected bool $started = false;

    /**
     * Konstruktor
     */
    public function __construct(
        protected FlashMessageInterface $flash,
        protected SessionConfiguration  $config
    )
    {
    }


    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Konfiguriere die PHP-Session
        $this->configureSession();

        // Setze den Session-Namen
        session_name($this->config->name);

        // Starte die Session
        $this->started = session_start();

        if ($this->started) {
            // Prüfe, ob die Session gültig ist
            if (!$this->isValid()) {
                // Bei ungültiger Session: Neue starten
                $this->destroy();
                $this->started = session_start();
                $this->saveFingerprint();
            }

            // Session-Aktivität aktualisieren
            $this->checkActivity();

            // Regelmäßige Session-ID-Rotation
            $this->rotateId();

            // Lade Flash-Messages
            $this->flash->load();
        }

        return $this->started;
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
    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (!$this->started) {
            $this->start();
        }

        return session_regenerate_id($deleteOldSession);
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
    public function has(string $key): bool
    {
        if (!$this->started) {
            $this->start();
        }

        return isset($_SESSION[$key]);
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
    public function flash(string $key, mixed $value): static
    {
        $this->flash->add($key, $value);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->flash->get($key, $default);
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
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): static
    {
        if ($this->started) {
            throw new RuntimeException('Cannot change session name after the session has started');
        }

        $this->name = $name;

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
    public function flush(): bool
    {
        if (!$this->started) {
            $this->start();
        }

        // Aktualisiere den Zeitpunkt der letzten Aktivität
        $this->set('last_activity', time());

        // Schreibe die Session-Daten und beende sie
        return session_write_close();
    }

    /**
     * Konfiguriert die PHP-Session mit den Einstellungen aus der Konfiguration
     */
    protected function configureSession(): void
    {
        // Cookie-Parameter setzen
        session_set_cookie_params([
            'lifetime' => $this->config->lifetime,
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
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        // Prüfe, ob die Session gestartet wurde
        if (!$this->started) {
            return false;
        }

        // Prüfe den Fingerprint, falls aktiviert
        if ($this->config->fingerprintCheck && !$this->validateFingerprint()) {
            return false;
        }

        // Prüfe den Idle-Timeout
        if (!$this->checkActivity()) {
            return false;
        }

        return true;
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
    public function validateFingerprint(): bool
    {
        if (!$this->has('_fingerprint')) {
            return false;
        }

        $storedFingerprint = $this->get('_fingerprint');
        $currentFingerprint = $this->generateFingerprint();

        return hash_equals($storedFingerprint, $currentFingerprint);
    }

    /**
     * {@inheritdoc}
     */
    public function generateCsrfToken(string $key = 'csrf'): string
    {
        if (!$this->started) {
            $this->start();
        }

        // Generiere ein zufälliges Token
        $token = bin2hex(random_bytes(32));

        // Speichere das Token in der Session
        $_SESSION['_csrf'][$key] = $token;

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function validateCsrfToken(string $token, string $key = 'csrf'): bool
    {
        if (!$this->started) {
            $this->start();
        }

        // Prüfe, ob das Token existiert
        if (!isset($_SESSION['_csrf'][$key])) {
            return false;
        }

        $storedToken = $_SESSION['_csrf'][$key];

        // Lösche das Token nach der Überprüfung (einmaliges Token)
        unset($_SESSION['_csrf'][$key]);

        // Überprüfe das Token mit konstanter Zeit (verhindert Timing-Angriffe)
        return hash_equals($storedToken, $token);
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

        // Wenn last_rotation nicht gesetzt ist oder das Intervall überschritten wurde
        $lastRotation = $this->get('_last_rotation', 0);
        $now = time();

        if ($force || !$lastRotation || ($now - $lastRotation) > $this->config->regenerateIdInterval) {
            $result = $this->regenerate(true);

            if ($result) {
                $this->set('_last_rotation', $now);
            }

            return $result;
        }

        return false;
    }
}
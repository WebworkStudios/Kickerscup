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
     * Der Session-Name
     */
    protected string $name = 'app_session';

    /**
     * Flash-Message-Handler
     */
    protected FlashMessageInterface $flash;

    /**
     * Konstruktor
     */
    public function __construct(FlashMessageInterface $flash)
    {
        $this->flash = $flash;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Setze den Session-Namen
        session_name($this->name);

        // Starte die Session
        $this->started = session_start();

        // Lade Flash-Messages
        if ($this->started) {
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
}
<?php


declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;

#[Injectable]
class FlashMessage implements FlashMessageInterface
{
    /**
     * Der Session-Schlüssel für Flash-Messages
     */
    protected const FLASH_KEY = '_flash';

    /**
     * Der Session-Schlüssel für neue Flash-Messages
     */
    protected const FLASH_NEW = '_flash_new';

    /**
     * Konstruktor
     */
    public function __construct()
    {
        // Initialisiere Flash-Arrays, falls nötig
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }

        if (!isset($_SESSION[self::FLASH_NEW])) {
            $_SESSION[self::FLASH_NEW] = [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value): static
    {
        $_SESSION[self::FLASH_NEW][$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[self::FLASH_KEY][$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        $all = $_SESSION[self::FLASH_KEY];
        $_SESSION[self::FLASH_KEY] = [];

        return $all;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): static
    {
        $_SESSION[self::FLASH_KEY] = [];
        $_SESSION[self::FLASH_NEW] = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function load(): void
    {
        // Übertrage neue Flash-Messages in das aktuelle Array
        $_SESSION[self::FLASH_KEY] = $_SESSION[self::FLASH_NEW];
        $_SESSION[self::FLASH_NEW] = [];
    }

    /**
     * {@inheritdoc}
     */
    public function keep(): void
    {
        // Übertrage aktuelle Flash-Messages in das neue Array
        foreach ($_SESSION[self::FLASH_KEY] as $key => $value) {
            $_SESSION[self::FLASH_NEW][$key] = $value;
        }
    }
}
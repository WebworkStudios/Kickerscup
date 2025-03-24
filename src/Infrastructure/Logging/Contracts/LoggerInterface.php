<?php


declare(strict_types=1);

namespace App\Infrastructure\Logging\Contracts;

use Throwable;

/**
 * Interface für Logger
 */
interface LoggerInterface
{
    /**
     * Protokolliert eine Nachricht mit dem Debug-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Info-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Notice-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Warning-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Error-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Critical-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Alert-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit dem Emergency-Level
     *
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Protokolliert eine Nachricht mit einem beliebigen Level
     *
     * @param string $level Das Log-Level
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Protokolliert eine Exception
     *
     * @param Throwable $exception Die zu protokollierende Exception
     * @param string $level Das Log-Level
     * @param string $message Optionale zusätzliche Nachricht
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function exception(Throwable $exception, string $level = 'error', string $message = '', array $context = []): void;
}
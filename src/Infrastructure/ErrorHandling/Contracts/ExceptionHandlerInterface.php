<?php


declare(strict_types=1);

namespace App\Infrastructure\ErrorHandling\Contracts;

use Throwable;

/**
 * Interface für den zentralen Exception-Handler
 */
interface ExceptionHandlerInterface
{
    /**
     * Behandelt eine Exception
     *
     * @param Throwable $exception Die aufgetretene Exception
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function handle(Throwable $exception, array $context = []): void;

    /**
     * Meldet eine Exception (zu Logging, Monitoring-Diensten, etc.)
     *
     * @param Throwable $exception Die aufgetretene Exception
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return void
     */
    public function report(Throwable $exception, array $context = []): void;

    /**
     * Registriert Callback-Funktionen für bestimmte Exception-Typen
     *
     * @param string $exceptionClass Die Exception-Klasse
     * @param callable $handler Der Handler für diese Exception
     * @return static
     */
    public function registerHandler(string $exceptionClass, callable $handler): static;

    /**
     * Transformiert eine Exception in eine andere
     *
     * @param Throwable $exception Die zu transformierende Exception
     * @param array<string, mixed> $context Zusätzlicher Kontext
     * @return Throwable Die transformierte Exception
     */
    public function transform(Throwable $exception, array $context = []): Throwable;

    /**
     * Registriert eine Transformationsregel
     *
     * @param string $sourceException Die Quell-Exception-Klasse
     * @param callable $transformer Funktion, die eine Exception transformiert
     * @return static
     */
    public function registerTransformer(string $sourceException, callable $transformer): static;

    /**
     * Registriert einen Sammler für zusätzliche Fehlerinformationen
     *
     * @param callable $collector Funktion, die Kontextinformationen sammelt
     * @param string|null $name Optionaler Name für den Sammler
     * @return static
     */
    public function registerContextCollector(callable $collector, ?string $name = null): static;
}
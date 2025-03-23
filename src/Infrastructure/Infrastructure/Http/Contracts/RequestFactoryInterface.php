<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Contracts;

use App\Infrastructure\Http\Request;

/**
 * Interface für Request Factory
 */
interface RequestFactoryInterface
{
    /**
     * Erstellt eine Request-Instanz aus den globalen Variablen
     */
    public function createFromGlobals(): Request;

    /**
     * Erstellt eine Request-Instanz mit benutzerdefinierten Parametern
     *
     * @param array<string, string> $queryParams
     * @param array<string, mixed> $postData
     * @param array<string, string> $cookies
     * @param array<string, array<string, mixed>> $files
     * @param array<string, string> $serverParams
     */
    public function create(
        string $method,
        string $uri,
        array  $queryParams = [],
        array  $postData = [],
        array  $cookies = [],
        array  $files = [],
        array  $serverParams = []
    ): Request;
}
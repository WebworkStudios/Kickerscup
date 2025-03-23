<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Factory;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestFactoryInterface;
use App\Infrastructure\Http\Request;

/**
 * Factory für Request-Objekte
 */
#[Injectable]
class RequestFactory implements RequestFactoryInterface
{
    /**
     * Erstellt eine Request-Instanz aus den globalen Variablen
     */
    public function createFromGlobals(): Request
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Overriding der HTTP-Methode durch _method-Parameter oder X-HTTP-Method-Override Header
        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        return new Request(
            $method,
            $uri,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER
        );
    }

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
    ): Request
    {
        return new Request(
            $method,
            $uri,
            $queryParams,
            $postData,
            $cookies,
            $files,
            $serverParams
        );
    }
}
<?php

declare(strict_types=1);

/**
 * API-optimierte Hilfsfunktionen für das Framework
 */

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\ResponseFactory;
use JetBrains\PhpStorm\NoReturn;

/**
 * Globale Container-Instanz
 */
$container = null;

/**
 * Setzt den globalen Container
 *
 * @param Container $container Container-Instanz
 * @return void
 */
function setContainer(Container $container): void
{
    global $container;
    $container = $container;
}

/**
 * Holt einen Service aus dem Container
 *
 * @param string|null $abstract Abstrakter Klassenname oder null für den Container selbst
 * @return mixed Service oder Container
 * @throws Exception Wenn der Container nicht initialisiert ist
 */
function app(?string $abstract = null): mixed
{
    global $container;

    if ($container === null) {
        throw new Exception('Container is not initialized. Call setContainer() first.');
    }

    return $abstract === null ? $container : $container->make($abstract);
}

/**
 * Holt einen Konfigurationswert
 *
 * @param string $key Schlüssel (z.B. 'app.debug')
 * @param mixed $default Standardwert
 * @return mixed Konfigurationswert
 */
function config(string $key, mixed $default = null): mixed
{
    static $configCache = [];

    $parts = explode('.', $key);
    $file = array_shift($parts);

    if (!isset($configCache[$file])) {
        $configPath = dirname(__DIR__) . '/config/' . $file . '.php';

        if (!file_exists($configPath)) {
            return $default;
        }

        $configCache[$file] = require $configPath;
    }

    $config = $configCache[$file];

    foreach ($parts as $part) {
        if (!is_array($config) || !array_key_exists($part, $config)) {
            return $default;
        }

        $config = $config[$part];
    }

    return $config;
}

/**
 * Holt einen Umgebungswert
 *
 * @param string $key Schlüssel
 * @param mixed $default Standardwert
 * @return mixed Umgebungswert
 */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => $value,
    };
}

/**
 * Erstellt eine ResponseFactory-Instanz
 *
 * @return ResponseFactory
 * @throws Exception
 */
function response(): ResponseFactory
{
    return app(ResponseFactory::class);
}

/**
 * Holt die aktuelle Request-Instanz
 *
 * @return Request
 * @throws Exception
 */
function request(): Request
{
    return app(Request::class);
}

/**
 * Gibt den Basis-Pfad oder einen Unterpfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 * @throws Exception
 */
function base_path(string $path = ''): string
{
    $basePath = app('base_path');

    return empty($path)
        ? $basePath
        : $basePath . '/' . ltrim($path, '/');
}

/**
 * Gibt den Storage-Pfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 * @throws Exception
 */
function storage_path(string $path = ''): string
{
    return base_path('storage/' . ltrim($path, '/'));
}

/**
 * Übersetzt einen Text (für API minimiert)
 *
 * @param string $key Schlüssel
 * @param array $replace Zu ersetzende Werte
 * @return string Übersetzter Text
 * @throws Exception
 */
function trans(string $key, array $replace = []): string
{
    static $translator = null;

    if ($translator === null) {
        $cache = app()->has('App\Core\Cache\Cache')
            ? app('App\Core\Cache\Cache')
            : null;

        $translator = new \App\Core\Translation\Translator(
            config('app.locale', 'en'),
            config('app.fallback_locale', 'en'),
            $cache
        );
    }

    return $translator->get($key, $replace);
}

/**
 * Generiert einen CSRF-Token für API
 *
 * @return string Token
 * @throws Exception
 */
function csrf_token(): string
{
    return app('App\Core\Security\Csrf')->generateApiToken()['token'];
}

/**
 * Loggt eine Nachricht im API-Log
 *
 * @param string $message Nachricht
 * @param array $context Kontext
 * @param string $level Log-Level
 * @return void
 * @throws Exception
 */
function app_log(string $message, array $context = [], string $level = 'info'): void
{
    $date = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[$date] [$level] $message$contextStr" . PHP_EOL;

    $path = storage_path('logs/api.log');
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($path, $logMessage, FILE_APPEND);
}

/**
 * Gibt einen Wert oder den Standardwert zurück
 *
 * @param mixed $value Wert
 * @param mixed $default Standardwert
 * @return mixed Wert oder Standardwert
 */
function value(mixed $value, mixed $default = null): mixed
{
    return $value ?? $default;
}

/**
 * Gibt einen formatierten Wert als JSON zurück und beendet die Anwendung
 *
 * @param mixed $value Wert
 * @param int $status HTTP-Statuscode
 * @param array $headers HTTP-Header
 * @return void
 * @throws Exception
 */
#[NoReturn] function json(mixed $value, int $status = 200, array $headers = []): void
{
    $response = response()->json($value, $status, $headers);
    $response->send();
    exit;
}
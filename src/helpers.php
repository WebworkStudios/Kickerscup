<?php

declare(strict_types=1);

/**
 * Hilfsfunktionen für das Framework
 */

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;

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
}

/**
 * Holt einen Service aus dem Container
 *
 * @param string|null $abstract Abstrakter Klassenname oder null für den Container selbst
 * @return mixed Service oder Container
 */
function app(?string $abstract = null): mixed
{
    global $container;

    if ($abstract === null) {
        return $container;
    }

    return $container->make($abstract);
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
    $parts = explode('.', $key);
    $file = array_shift($parts);

    $configPath = dirname(__DIR__) . '/config/' . $file . '.php';

    if (!file_exists($configPath)) {
        return $default;
    }

    $config = require $configPath;

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

    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    return $value;
}

/**
 * Erstellt eine ResponseFactory-Instanz
 *
 * @return ResponseFactory
 */
function response(): ResponseFactory
{
    return app(ResponseFactory::class);
}

/**
 * Holt die aktuelle Request-Instanz
 *
 * @return Request
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
 */
function base_path(string $path = ''): string
{
    $basePath = app('base_path');

    if (empty($path)) {
        return $basePath;
    }

    return $basePath . '/' . ltrim($path, '/');
}

/**
 * Gibt den Storage-Pfad oder einen Unterpfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 */
function storage_path(string $path = ''): string
{
    return base_path('storage/' . ltrim($path, '/'));
}

/**
 * Gibt den Ressourcen-Pfad oder einen Unterpfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 */
function resource_path(string $path = ''): string
{
    return base_path('resources/' . ltrim($path, '/'));
}

/**
 * Gibt den Public-Pfad oder einen Unterpfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 */
function public_path(string $path = ''): string
{
    return base_path('public/' . ltrim($path, '/'));
}

/**
 * Gibt einen Asset-Pfad zurück
 *
 * @param string $path Pfad
 * @return string Vollständiger Pfad
 */
function asset(string $path): string
{
    return config('app.url') . '/' . ltrim($path, '/');
}

/**
 * Übersetzt einen Text
 *
 * @param string $key Schlüssel
 * @param array $replace Zu ersetzende Werte
 * @param string|null $locale Sprache
 * @return string Übersetzter Text
 */
function trans(string $key, array $replace = [], ?string $locale = null): string
{
    // Hier eine einfache Implementierung, später erweitern
    $locale = $locale ?? config('app.locale', 'de');
    $parts = explode('.', $key);
    $file = array_shift($parts);

    $path = resource_path("lang/$locale/$file.php");

    if (!file_exists($path)) {
        $path = resource_path('lang/' . config('app.fallback_locale', 'en') . "/$file.php");

        if (!file_exists($path)) {
            return $key;
        }
    }

    $translations = require $path;

    $translation = $translations;

    foreach ($parts as $part) {
        if (!is_array($translation) || !array_key_exists($part, $translation)) {
            return $key;
        }

        $translation = $translation[$part];
    }

    if (!is_string($translation)) {
        return $key;
    }

    // Platzhalter ersetzen
    foreach ($replace as $search => $value) {
        $translation = str_replace(':' . $search, $value, $translation);
    }

    return $translation;
}

/**
 * Maskiert HTML-Zeichen
 *
 * @param string $value Zu maskierender Text
 * @return string Maskierter Text
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generiert eine URL für eine Route
 *
 * @param string $name Routenname
 * @param array $parameters Parameter
 * @return string URL
 */
function route(string $name, array $parameters = []): string
{
    $router = app()->make('App\Core\Routing\Router');
    return $router->generateUrl($name, $parameters);
}

/**
 * Gibt einen formatierten Wert als JSON zurück und beendet die Anwendung
 *
 * @param mixed $value Wert
 * @param int $status HTTP-Statuscode
 * @param array $headers HTTP-Header
 * @return void
 */
function json(mixed $value, int $status = 200, array $headers = []): void
{
    $response = response()->json($value, $status, $headers);
    $response->send();
    exit;
}

/**
 * Leitet zu einer URL weiter und beendet die Anwendung
 *
 * @param string $url URL
 * @param int $status HTTP-Statuscode
 * @param array $headers HTTP-Header
 * @return void
 */
function redirect(string $url, int $status = 302, array $headers = []): void
{
    $response = response()->redirect($url, $status, $headers);
    $response->send();
    exit;
}

/**
 * Generiert eine CSRF-Token-Eingabe
 *
 * @return string HTML-Input
 */
function csrf_field(): string
{
    $token = app()->make('App\Core\Security\Csrf')->getToken();

    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Generiert einen CSRF-Token
 *
 * @return string Token
 */
function csrf_token(): string
{
    return app()->make('App\Core\Security\Csrf')->getToken() ?? '';
}

/**
 * Generiert eine Methoden-Eingabe
 *
 * @param string $method HTTP-Methode
 * @return string HTML-Input
 */
function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e($method) . '">';
}

/**
 * Loggt eine Nachricht ins Framework-Logfile
 *
 * Wir verwenden app_log anstelle von log, um Konflikte mit anderen Funktionen zu vermeiden
 *
 * @param string $message Nachricht
 * @param array $context Kontext
 * @param string $level Log-Level
 * @return void
 */
function app_log(string $message, array $context = [], string $level = 'info'): void
{
    // Hier eine einfache Implementierung, später erweitern
    $date = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[$date] [$level] $message$contextStr" . PHP_EOL;

    $path = storage_path('logs/app.log');
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
 * Debug-Funktion
 *
 * @param mixed $value Wert
 * @return void
 */
function dd(mixed ...$values): void
{
    foreach ($values as $value) {
        var_dump($value);
    }

    exit;
}
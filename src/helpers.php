<?php

declare(strict_types=1);

/**
 * Hilfsfunktionen für das Framework
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
        throw new Exception('Container is not initialized. Make sure setContainer() is called before using app().');
    }

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
    static $configCache = [];

    $parts = explode('.', $key);
    $file = array_shift($parts);

    // Konfiguration aus dem Cache laden, wenn vorhanden
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
 * @throws Exception
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
 * @throws Exception
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
 * @throws Exception
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
 * @throws Exception
 */
function trans(string $key, array $replace = [], ?string $locale = null): string
{
    static $translator = null;

    if ($translator === null) {
        // Translator-Instanz mit Cache laden
        $cache = null;
        if (app()->has('App\Core\Cache\Cache')) {
            $cache = app('App\Core\Cache\Cache');
        }

        $translator = new \App\Core\Translation\Translator(
            config('app.locale', 'de'),
            config('app.fallback_locale', 'en'),
            $cache
        );
    }

    return $translator->get($key, $replace, $locale);
}

/**
 * Übersetzt einen Text mit Pluralisierung
 *
 * @param string $key Schlüssel
 * @param int $number Anzahl für Pluralentscheidung
 * @param array $replace Zu ersetzende Werte
 * @param string|null $locale Sprache
 * @return string Übersetzter Text
 * @throws Exception
 */
function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
{
    static $translator = null;

    if ($translator === null) {
        $cache = null;
        if (app()->has('App\Core\Cache\Cache')) {
            $cache = app('App\Core\Cache\Cache');
        }

        $translator = new \App\Core\Translation\Translator(
            config('app.locale', 'de'),
            config('app.fallback_locale', 'en'),
            $cache
        );
    }

    return $translator->choice($key, $number, $replace, $locale);
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

// src/helpers.php

/**
 * Generiert eine URL für eine Route
 *
 * @param string $name Routenname
 * @param array $parameters Parameter
 * @return string URL
 * @throws Exception Wenn die Route nicht gefunden wurde
 */
function route(string $name, array $parameters = []): string
{
    $router = app('App\Core\Routing\Router');
    $routes = $router->getRoutes();

    $route = $routes->findByName($name);

    if ($route === null) {
        throw new Exception("Route mit dem Namen '$name' wurde nicht gefunden.");
    }

    $uri = $route->getUri();

    // Parameter in der URI ersetzen
    foreach ($parameters as $key => $value) {
        $uri = str_replace("{{$key}}", (string)$value, $uri);
    }

    // Basis-URL hinzufügen
    $baseUrl = config('app.url', '');

    // Domain berücksichtigen, falls vorhanden
    $domain = $route->getDomain();
    if ($domain !== null) {
        $baseUrl = preg_replace('/^https?:\/\/[^\/]+/i', "http://$domain", $baseUrl);
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($uri, '/');
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

/**
 * Leitet zu einer URL weiter und beendet die Anwendung
 *
 * @param string $url URL
 * @param int $status HTTP-Statuscode
 * @param array $headers HTTP-Header
 * @return void
 * @throws Exception
 */
#[NoReturn] function redirect(string $url, int $status = 302, array $headers = []): void
{
    $response = response()->redirect($url, $status, $headers);
    $response->send();
    exit;
}

/**
 * Generiert eine CSRF-Token-Eingabe
 *
 * @return string HTML-Input
 * @throws Exception
 */
function csrf_field(): string
{
    $token = app()->make('App\Core\Security\Csrf')->getToken();

    // Sicherstellen, dass der Token nicht null ist
    $token = $token ?? '';

    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Generiert einen CSRF-Token
 *
 * @return string Token
 * @throws Exception
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
 * @throws Exception
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
 * @param mixed ...$values
 * @return void
 */
#[NoReturn] function dd(mixed ...$values): void
{
    foreach ($values as $value) {
        var_dump($value);
    }

    exit;
}
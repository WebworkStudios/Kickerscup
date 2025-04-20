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

/**
 * Wirft eine NotFoundException
 *
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @throws \App\Core\Error\NotFoundException
 */
function throw_not_found(string $message, ?string $errorCode = null): void
{
    throw new \App\Core\Error\NotFoundException($message, $errorCode);
}

/**
 * Wirft eine ValidationException
 *
 * @param string $message Fehlermeldung
 * @param array $errors Validierungsfehler
 * @param string|null $errorCode Fehlercode
 * @throws \App\Core\Error\ValidationException
 */
function throw_validation(string $message, array $errors = [], ?string $errorCode = null): void
{
    throw new \App\Core\Error\ValidationException($message, $errors, $errorCode);
}

/**
 * Wirft eine AuthenticationException
 *
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @throws \App\Core\Error\AuthenticationException
 */
function throw_unauthenticated(string $message = 'Nicht authentifiziert.', ?string $errorCode = null): void
{
    throw new \App\Core\Error\AuthenticationException($message, $errorCode);
}

/**
 * Wirft eine AuthorizationException
 *
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @throws \App\Core\Error\AuthorizationException
 */
function throw_forbidden(string $message = 'Zugriff verweigert.', ?string $errorCode = null): void
{
    throw new \App\Core\Error\AuthorizationException($message, $errorCode);
}

/**
 * Wirft eine BadRequestException
 *
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @throws \App\Core\Error\BadRequestException
 */
function throw_bad_request(string $message, ?string $errorCode = null): void
{
    throw new \App\Core\Error\BadRequestException($message, $errorCode);
}

/**
 * Gibt eine Erfolgsantwort zurück
 *
 * @param mixed $data Daten
 * @param int $status HTTP-Status
 * @param array $headers HTTP-Header
 * @return \App\Core\Http\Response
 */
function api_success(mixed $data = null, int $status = 200, array $headers = []): \App\Core\Http\Response
{
    return app(ResponseFactory::class)->success($data, $status, $headers);
}

/**
 * Gibt eine Fehlerantwort zurück
 *
 * @param string $message Fehlermeldung
 * @param string $errorCode Fehlercode
 * @param array $details Fehlerdetails
 * @param int $status HTTP-Status
 * @param array $headers HTTP-Header
 * @return \App\Core\Http\Response
 */
function api_error(
    string $message,
    string $errorCode = 'ERROR',
    array  $details = [],
    int    $status = 400,
    array  $headers = []
): \App\Core\Http\Response
{
    return app(ResponseFactory::class)->error($message, $errorCode, $details, $status, $headers);
}

/**
 * Gibt eine API-Ressource zurück
 *
 * @param mixed $model Modell
 * @param string $resource Ressourcenklasse
 * @return array
 */
function api_resource(mixed $model, string $resource): array
{
    return app(\App\Core\Api\ResourceFactory::class)->make($model, $resource);
}

/**
 * Gibt eine API-Ressourcensammlung zurück
 *
 * @param array $models Modelle
 * @param string $resource Ressourcenklasse
 * @return array
 */
function api_collection(array $models, string $resource): array
{
    return app(\App\Core\Api\ResourceFactory::class)->collection($models, $resource);
}

/**
 * Prüft, ob eine Anforderung existiert und wirft anderenfalls eine 404-Exception
 *
 * @param mixed $value Zu prüfender Wert
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @return mixed Der geprüfte Wert
 * @throws \App\Core\Error\NotFoundException
 */
function require_found(mixed $value, string $message = 'Die angeforderte Ressource wurde nicht gefunden.', ?string $errorCode = null): mixed
{
    if (empty($value)) {
        throw new \App\Core\Error\NotFoundException($message, $errorCode);
    }

    return $value;
}

/**
 * Überprüft, ob ein Nutzer authentifiziert ist
 *
 * @param bool $condition Bedingung, die erfüllt sein muss
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @return bool Immer true (wirft Exception, wenn false)
 * @throws \App\Core\Error\AuthenticationException
 */
function require_auth(bool $condition, string $message = 'Nicht authentifiziert.', ?string $errorCode = null): bool
{
    if (!$condition) {
        throw new \App\Core\Error\AuthenticationException($message, $errorCode);
    }

    return true;
}

/**
 * Prüft eine Berechtigung und wirft anderenfalls eine 403-Exception
 *
 * @param bool $condition Bedingung, die erfüllt sein muss
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @return bool Immer true (wirft Exception, wenn false)
 * @throws \App\Core\Error\AuthorizationException
 */
function require_permission(bool $condition, string $message = 'Zugriff verweigert.', ?string $errorCode = null): bool
{
    if (!$condition) {
        throw new \App\Core\Error\AuthorizationException($message, $errorCode);
    }

    return true;
}

/**
 * Gibt eine ApiResource-Instanz zurück
 *
 * @return \App\Core\Api\ApiResource
 */
function api(): \App\Core\Api\ApiResource
{
    return app(\App\Core\Api\ApiResource::class);
}

/**
 * Prüft, ob eine Bedingung wahr ist und wirft anderenfalls eine Exception
 *
 * @param bool $condition Bedingung, die erfüllt sein muss
 * @param string $message Fehlermeldung
 * @param string|null $errorCode Fehlercode
 * @return bool Immer true (wirft Exception, wenn false)
 * @throws \App\Core\Error\BadRequestException
 */
function require_true(bool $condition, string $message = 'Die Bedingung wurde nicht erfüllt.', ?string $errorCode = null): bool
{
    if (!$condition) {
        throw new \App\Core\Error\BadRequestException($message, $errorCode);
    }

    return true;
}

/**
 * Validiert Daten mit dem Validator und wirft bei Fehlern eine ValidationException
 *
 * @param array $data Zu validierende Daten
 * @param array $rules Validierungsregeln
 * @param array $messages Benutzerdefinierte Fehlermeldungen
 * @return array Validierte Daten
 * @throws \App\Core\Error\ValidationException
 */
function validate(array $data, array $rules, array $messages = []): array
{
    $validator = app(\App\Core\Validation\Validator::class);
    return $validator->validateOrFail($data, $rules, $messages);
}

/**
 * Erzeugt einen Debug-Dump einer Variable
 *
 * @param mixed $var Zu dumpende Variable
 * @param bool $exit Nach Dump beenden
 * @return void
 */
function dd(mixed $var, bool $exit = true): void
{
    // Nur im Debug-Modus dumpen
    if (!config('app.debug', false)) {
        return;
    }

    ob_start();
    var_dump($var);
    $output = ob_get_clean();

    // Im CLI-Modus ausgeben
    if (PHP_SAPI === 'cli') {
        echo $output . PHP_EOL;
    } // Im Web-Modus in pre-Tags ausgeben
    else {
        echo '<pre style="background:#f8f8f8;color:#333;font-size:14px;padding:10px;border-radius:5px;margin:10px 0;border:1px solid #ddd;overflow:auto;">' .
            htmlspecialchars($output, ENT_QUOTES) .
            '</pre>';
    }

    if ($exit) {
        exit(1);
    }
}

/**
 * Erzeugt eine URL für die Anwendung
 *
 * @param string $path Pfad
 * @param array $query Query-Parameter
 * @return string URL
 */
function url(string $path = '', array $query = []): string
{
    $basePath = app('base_url') ?? config('app.url', '');
    $path = '/' . ltrim($path, '/');

    $url = rtrim($basePath, '/') . $path;

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

/**
 * Generiert eine URL für eine benannte Route
 *
 * @param string $name Routenname
 * @param array $parameters Routenparameter
 * @param array $query Query-Parameter
 * @return string URL
 * @throws \Exception wenn die Route nicht gefunden wird
 */
function route(string $name, array $parameters = [], array $query = []): string
{
    $router = app(\App\Core\Routing\Router::class);
    $route = $router->getRoutes()->findByName($name);

    if (!$route) {
        throw new \Exception("Route mit Namen '$name' wurde nicht gefunden.");
    }

    $uri = $route->getUri();

    // Parameter in URI einsetzen
    foreach ($parameters as $key => $value) {
        $uri = str_replace("{{$key}}", (string)$value, $uri);
    }

    return url($uri, $query);
}

/**
 * Prüft PHP-Code auf Syntax-Fehler
 *
 * @param string $code PHP-Code
 * @return bool|string True bei gültiger Syntax, sonst Fehlermeldung
 */
function check_php_syntax(string $code): bool|string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'syntax_check_');
    file_put_contents($tempFile, $code);

    $output = [];
    $return = 0;

    exec("php -l {$tempFile} 2>&1", $output, $return);
    unlink($tempFile);

    if ($return === 0) {
        return true;
    }

    return implode(PHP_EOL, $output);
}

/**
 * Erzeugt einen Hash mit den neuen xxHash-Funktionen von PHP 8.4
 *
 * @param string $data Zu hashende Daten
 * @return string Hash
 */
function xxhash(string $data): string
{
    return hash('xxh3', $data);
}

/**
 * Erzeugt eine JSON-Web-Token für die API-Authentifizierung
 *
 * @param int $userId Benutzer-ID
 * @param array $claims Zusätzliche Claims
 * @param int|null $lifetime Lebensdauer in Sekunden
 * @return string JWT-Token
 */
function generate_jwt(int $userId, array $claims = [], ?int $lifetime = null): string
{
    return app(\App\Core\Security\JWTAuth::class)->createToken($userId, $claims, $lifetime);
}

/**
 * Validiert ein JSON-Web-Token
 *
 * @param string $token JWT-Token
 * @return array|null Claims oder null, wenn ungültig
 */
function validate_jwt(string $token): ?array
{
    return app(\App\Core\Security\JWTAuth::class)->validateToken($token);
}

/**
 * Extrahiert die Benutzer-ID aus einem JSON-Web-Token
 *
 * @param string $token JWT-Token
 * @return int|null Benutzer-ID oder null, wenn ungültig
 */
function get_user_id_from_jwt(string $token): ?int
{
    return app(\App\Core\Security\JWTAuth::class)->getUserIdFromToken($token);
}
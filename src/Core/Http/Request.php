<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Request-Klasse
 *
 * Repräsentiert einen HTTP-Request
 */
class Request
{
    private ?array $jsonCache = null;

    /**
     * Konstruktor
     *
     * @param string $method HTTP-Methode
     * @param string $uri URI
     * @param array $headers HTTP-Header
     * @param array $cookies Cookies
     * @param array $query GET-Parameter
     * @param array $request POST-Parameter
     * @param array $files Hochgeladene Dateien
     * @param string $content Request-Body
     * @param string $host Host
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $headers = [],
        private readonly array  $cookies = [],
        private readonly array  $query = [],
        private readonly array  $request = [],
        private readonly array  $files = [],
        private readonly string $content = '',
        private readonly string $host = ''
    )
    {
    }

    /**
     * Erstellt einen Request aus globalen Variablen
     *
     * @return self
     */
    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            getallheaders() ?: [],
            $_COOKIE,
            $_GET,
            $_POST,
            $_FILES,
            file_get_contents('php://input') ?: '',
            $_SERVER['HTTP_HOST'] ?? ''
        );
    }

    /**
     * Gibt die HTTP-Methode zurück
     *
     * @return string HTTP-Methode
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Gibt den URI zurück
     *
     * @return string URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Gibt den Host zurück
     *
     * @return string Host
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Gibt alle Header zurück
     *
     * @return array Header
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Gibt alle Cookies zurück
     *
     * @return array Cookies
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Gibt ein Cookie zurück
     *
     * @param string $name Name des Cookies
     * @param mixed $default Standardwert, wenn das Cookie nicht existiert
     * @return mixed Wert des Cookies oder Standardwert
     */
    public function getCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Prüft, ob ein Cookie existiert
     *
     * @param string $name Name des Cookies
     * @return bool True, wenn das Cookie existiert, sonst false
     */
    public function hasCookie(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    /**
     * Gibt alle GET-Parameter zurück
     *
     * @return array GET-Parameter
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Gibt einen GET-Parameter zurück
     *
     * @param string $name Name des Parameters
     * @param mixed $default Standardwert, wenn der Parameter nicht existiert
     * @return mixed Wert des Parameters oder Standardwert
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->getFromArray($this->query, $name, $default);
    }

    private function getFromArray(array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }

    /**
     * Prüft, ob ein GET-Parameter existiert
     *
     * @param string $name Name des Parameters
     * @return bool True, wenn der Parameter existiert, sonst false
     */
    public function hasQueryParam(string $name): bool
    {
        return isset($this->query[$name]);
    }

    /**
     * Gibt alle POST-Parameter zurück
     *
     * @return array POST-Parameter
     */
    public function getPost(): array
    {
        return $this->request;
    }

    /**
     * Gibt einen POST-Parameter zurück
     *
     * @param string $name Name des Parameters
     * @param mixed $default Standardwert, wenn der Parameter nicht existiert
     * @return mixed Wert des Parameters oder Standardwert
     */
    public function getPostParam(string $name, mixed $default = null): mixed
    {
        return $this->getFromArray($this->request, $name, $default);
    }

    /**
     * Prüft, ob ein POST-Parameter existiert
     *
     * @param string $name Name des Parameters
     * @return bool True, wenn der Parameter existiert, sonst false
     */
    public function hasPostParam(string $name): bool
    {
        return isset($this->request[$name]);
    }

    /**
     * Gibt einen Parameter (GET, POST oder JSON) zurück
     *
     * @param string $name Name des Parameters
     * @param mixed $default Standardwert, wenn der Parameter nicht existiert
     * @return mixed Wert des Parameters oder Standardwert
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->input()[$name] ?? $default;
    }

    /**
     * Gibt alle Input-Parameter (GET, POST, JSON) zurück
     *
     * @return array Alle Input-Parameter
     */
    public function input(): array
    {
        // Wenn es sich um einen JSON-Request handelt, JSON-Daten verwenden
        if ($this->isJson()) {
            $json = $this->getJson();

            if (is_array($json)) {
                return array_merge($this->query, $json);
            }
        }

        return $this->all();
    }

    /**
     * Prüft, ob der Request ein JSON-Request ist
     *
     * @return bool True, wenn der Request ein JSON-Request ist, sonst false
     */
    public function isJson(): bool
    {
        return $this->hasHeader('Content-Type') &&
            strpos(strtolower($this->getHeader('Content-Type')), 'application/json') !== false;
    }

    /**
     * Prüft, ob ein Header existiert
     *
     * @param string $name Name des Headers
     * @return bool True, wenn der Header existiert, sonst false
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Gibt einen Header zurück
     *
     * @param string $name Name des Headers
     * @param mixed $default Standardwert, wenn der Header nicht existiert
     * @return mixed Wert des Headers oder Standardwert
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Gibt den Request-Body als JSON zurück
     *
     * @param bool $assoc True, um ein assoziatives Array zurückzugeben, false für ein Objekt
     * @return mixed Decodiertes JSON oder null, wenn der Body kein gültiges JSON ist
     */
    public function getJson(bool $assoc = true): mixed
    {
        if ($this->jsonCache === null) {
            $this->jsonCache = json_decode($this->content, true) ?: null;
        }

        if (!$assoc && is_array($this->jsonCache)) {
            return json_decode(json_encode($this->jsonCache));
        }

        return $this->jsonCache;
    }

    /**
     * Gibt alle Parameter (GET und POST) zurück
     *
     * @return array Alle Parameter
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * Prüft, ob ein Parameter (GET, POST oder JSON) existiert
     *
     * @param string $name Name des Parameters
     * @return bool True, wenn der Parameter existiert, sonst false
     */
    public function has(string $name): bool
    {
        return isset($this->input()[$name]);
    }

    /**
     * Gibt alle hochgeladenen Dateien zurück
     *
     * @return array Hochgeladene Dateien
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Gibt eine hochgeladene Datei zurück
     *
     * @param string $name Name der Datei
     * @param mixed $default Standardwert, wenn die Datei nicht existiert
     * @return mixed Datei oder Standardwert
     */
    public function getFile(string $name, mixed $default = null): mixed
    {
        return $this->files[$name] ?? $default;
    }

    /**
     * Prüft, ob eine hochgeladene Datei existiert
     *
     * @param string $name Name der Datei
     * @return bool True, wenn die Datei existiert, sonst false
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]);
    }

    /**
     * Gibt den Request-Body zurück
     *
     * @return string Request-Body
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Prüft, ob der Request ein AJAX-Request ist
     *
     * @return bool True, wenn der Request ein AJAX-Request ist, sonst false
     */
    public function isAjax(): bool
    {
        return $this->hasHeader('X-Requested-With') &&
            strtolower($this->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Prüft, ob der Request eine bestimmte HTTP-Methode hat
     *
     * @param string $method HTTP-Methode
     * @return bool True, wenn der Request die Methode hat, sonst false
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

}
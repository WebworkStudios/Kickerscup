<?php


declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Contracts\RequestInterface;

/**
 * HTTP Request Klasse
 */
class Request implements RequestInterface
{
    public string $path {
        get {
            return $this->_path;
        }
        set {
            // Normalisiere den Pfad beim Setzen
            $path = trim($value, '/');
            $this->_path = '/' . $path;
        }
    }
    /**
     * HTTP Methode
     */
    protected string $method;
    /**
     * Request URI
     */
    protected string $uri;
    /**
     * Query Parameter
     *
     * @var array<string, string>
     */
    protected array $queryParams;
    /**
     * Post Daten
     *
     * @var array<string, mixed>
     */
    protected array $postData;
    /**
     * Cookies
     *
     * @var array<string, string>
     */
    protected array $cookies;
    /**
     * Files
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $files;
    /**
     * Server Parameter
     *
     * @var array<string, string>
     */
    protected array $serverParams;
    /**
     * Headers
     *
     * @var array<string, string>
     */
    protected array $headers;
    /**
     * Raw Body
     */
    protected ?string $rawBody = null;
    private string $_path;

    /**
     * Konstruktor
     *
     * @param array<string, string> $queryParams
     * @param array<string, mixed> $postData
     * @param array<string, string> $cookies
     * @param array<string, array<string, mixed>> $files
     * @param array<string, string> $serverParams
     */
    public function __construct(
        string $method,
        string $uri,
        array  $queryParams = [],
        array  $postData = [],
        array  $cookies = [],
        array  $files = [],
        array  $serverParams = []
    )
    {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->queryParams = $queryParams;
        $this->postData = $postData;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->serverParams = $serverParams;
        $this->headers = $this->parseHeaders($serverParams);
    }

    /**
     * Parst die HTTP-Headers aus den Server-Parametern
     *
     * @param array<string, string> $serverParams
     * @return array<string, string>
     */
    protected function parseHeaders(array $serverParams): array
    {
        $headers = [];

        foreach ($serverParams as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Gibt die HTTP-Methode zurück
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Überprüft, ob die Anfrage per GET erfolgte
     */
    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    /**
     * Überprüft, ob die aktuelle Anfrage eine bestimmte HTTP-Methode verwendet
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Überprüft, ob die Anfrage per POST erfolgte
     */
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * Überprüft, ob die Anfrage per PUT erfolgte
     */
    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    /**
     * Überprüft, ob die Anfrage per PATCH erfolgte
     */
    public function isPatch(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * Überprüft, ob die Anfrage per DELETE erfolgte
     */
    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    /**
     * Überprüft, ob die Anfrage eine sichere Methode ist (GET, HEAD, OPTIONS)
     */
    public function isSecureMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Gibt die URI zurück
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Gibt den Pfad aus der URI zurück (ohne Query-String)
     */
    public function getPath(): string
    {
        $uri = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        return $uri === '' ? '/' : $uri;
    }

    /**
     * Gibt den Query-String zurück
     */
    public function getQueryString(): ?string
    {
        return parse_url($this->uri, PHP_URL_QUERY);
    }

    /**
     * Gibt alle Query-Parameter zurück
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Gibt alle Post-Daten zurück
     *
     * @return array<string, mixed>
     */
    public function getPostData(): array
    {
        return $this->postData;
    }

    /**
     * Gibt ein Input-Parameter zurück (Sucht in GET und POST)
     */
    public function getInput(string $name, mixed $default = null): mixed
    {
        return $this->getPostParam($name, $this->getQueryParam($name, $default));
    }

    /**
     * Gibt einen bestimmten Post-Parameter zurück
     */
    public function getPostParam(string $name, mixed $default = null): mixed
    {
        return $this->postData[$name] ?? $default;
    }

    /**
     * Gibt einen bestimmten Query-Parameter zurück
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    /**
     * Überprüft, ob ein Input-Parameter existiert
     */
    public function hasInput(string $name): bool
    {
        return $this->hasQueryParam($name) || $this->hasPostParam($name);
    }

    /**
     * Überprüft, ob ein Query-Parameter existiert
     */
    public function hasQueryParam(string $name): bool
    {
        return isset($this->queryParams[$name]);
    }

    /**
     * Überprüft, ob ein Post-Parameter existiert
     */
    public function hasPostParam(string $name): bool
    {
        return isset($this->postData[$name]);
    }

    /**
     * Gibt alle Cookies zurück
     *
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Gibt ein Cookie zurück
     */
    public function getCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Überprüft, ob ein Cookie existiert
     */
    public function hasCookie(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    /**
     * Gibt alle Files zurück
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Gibt ein bestimmtes File zurück
     *
     * @return array<string, mixed>|null
     */
    public function getFile(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Überprüft, ob ein File existiert
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]['error']) &&
            $this->files[$name]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Gibt alle Server-Parameter zurück
     *
     * @return array<string, string>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * Gibt einen bestimmten Server-Parameter zurück
     */
    public function getServerParam(string $name, mixed $default = null): mixed
    {
        return $this->serverParams[$name] ?? $default;
    }

    /**
     * Überprüft, ob ein Server-Parameter existiert
     */
    public function hasServerParam(string $name): bool
    {
        return isset($this->serverParams[$name]);
    }

    /**
     * Gibt alle Headers zurück
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Überprüft, ob ein Header existiert
     */
    public function hasHeader(string $name): bool
    {
        $name = strtolower($name);
        return isset($this->headers[$name]);
    }

    /**
     * Überprüft, ob die Anfrage eine Ajax-Anfrage ist
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Gibt einen bestimmten Header zurück
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Gibt den geparsten JSON-Body zurück
     *
     * @return array<string, mixed>|null
     */
    public function getJsonBody(): ?array
    {
        if (!$this->isJson()) {
            return null;
        }

        $body = $this->getRawBody();
        if (empty($body)) {
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Überprüft, ob die Anfrage eine JSON-Anfrage ist
     */
    public function isJson(): bool
    {
        return $this->isContentType('application/json');
    }

    /**
     * Überprüft, ob die Anfrage einer bestimmten Content-Type hat
     */
    public function isContentType(string $contentType): bool
    {
        $currentContentType = $this->getContentType();
        if ($currentContentType === null) {
            return false;
        }

        // Vergleiche nur der MIME-Type ohne Parameter
        $currentContentType = explode(';', $currentContentType)[0];
        return strtolower($currentContentType) === strtolower($contentType);
    }

    /**
     * Gibt der Content-Type zurück
     */
    public function getContentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    /**
     * Gibt den Raw-Body zurück
     */
    public function getRawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }

        return $this->rawBody;
    }

    /**
     * Gibt die Client-IP-Adresse zurück
     */
    public function getClientIp(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($keys as $key) {
            if (isset($this->serverParams[$key])) {
                $ips = explode(',', $this->serverParams[$key]);
                $ip = trim(reset($ips));

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Überprüft, ob die Anfrage über HTTPS erfolgte
     */
    public function isSecure(): bool
    {
        if (isset($this->serverParams['HTTPS'])) {
            return $this->serverParams['HTTPS'] === 'on';
        }

        return false;
    }

    /**
     * Gibt die Subdomain zurück
     *
     * @param string $baseDomain Die Basis-Domain (z.B. 'example.com')
     * @return string|null Die Subdomain oder null, wenn keine existiert
     */
    public function getSubdomain(string $baseDomain): ?string
    {
        $host = $this->getHost();
        if ($host === null) {
            return null;
        }

        // Prüfen, ob es sich um eine Subdomain handelt
        if (str_ends_with($host, '.' . $baseDomain)) {
            return substr($host, 0, strlen($host) - strlen('.' . $baseDomain));
        }

        return null;
    }

    /**
     * Gibt den Host/Domain zurück
     */
    public function getHost(): ?string
    {
        return $this->serverParams['HTTP_HOST'] ?? null;
    }
}
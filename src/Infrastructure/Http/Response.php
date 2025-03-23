<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Contracts\ResponseInterface;

/**
 * HTTP Response Klasse
 */
class Response implements ResponseInterface
{
    /**
     * HTTP Statuscode
     */
    protected int $statusCode = 200;

    /**
     * HTTP Statustext
     */
    protected string $statusText = 'OK';

    /**
     * HTTP-Header
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Response-Body
     */
    protected string $body = '';

    /**
     * Cookies
     *
     * @var array<string, array{value: string, expire: int, path: string, domain: ?string, secure: bool, httpOnly: bool, sameSite: string}>
     */
    protected array $cookies = [];

    /**
     * Konstruktor
     */
    public function __construct(int $statusCode = 200, ?string $body = null)
    {
        $this->statusCode = $statusCode;
        $this->statusText = $this->getStatusTextForCode($statusCode);

        if ($body !== null) {
            $this->body = $body;
        }

        // Standard Content-Type setzen
        $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Setzt den HTTP-Statuscode
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        $this->statusText = $this->getStatusTextForCode($statusCode);

        return $this;
    }

    /**
     * Gibt den HTTP-Statuscode zurück
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Gibt den HTTP-Statustext zurück
     */
    public function getStatusText(): string
    {
        return $this->statusText;
    }

    /**
     * Setzt einen HTTP-Header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Fügt einen HTTP-Header hinzu (läßt mehrere Werte für den gleichen Header zu)
     */
    public function addHeader(string $name, string $value): self
    {
        if (isset($this->headers[$name])) {
            $this->headers[$name] .= ', ' . $value;
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Entfernt einen HTTP-Header
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    /**
     * Gibt einen HTTP-Header zurück
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Überprüft, ob ein HTTP-Header gesetzt ist
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Gibt alle HTTP-Header zurück
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Setzt alle HTTP-Header
     *
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = [];

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * Setzt den Response-Body
     */
    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Gibt den Response-Body zurück
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Setzt den Content-Type
     */
    public function setContentType(string $contentType): self
    {
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * Setzt ein Cookie
     */
    public function setCookie(
        string  $name,
        string  $value,
        int     $expire = 0,
        string  $path = '/',
        ?string $domain = null,
        bool    $secure = false,
        bool    $httpOnly = true,
        string  $sameSite = 'Lax'
    ): self
    {
        $this->cookies[$name] = [
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite
        ];

        return $this;
    }

    /**
     * Löscht ein Cookie
     */
    public function deleteCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        return $this->setCookie(
            $name,
            '',
            time() - 3600,
            $path,
            $domain,
            false,
            true
        );
    }

    /**
     * Setzt Cache-Control Header
     */
    public function setCache(int $seconds): self
    {
        if ($seconds <= 0) {
            return $this->setNoCache();
        }

        $this->setHeader('Pragma', 'public');
        $this->setHeader('Cache-Control', 'public, max-age=' . $seconds);
        $this->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');

        return $this;
    }

    /**
     * Deaktiviert das Caching
     */
    public function setNoCache(): self
    {
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->setHeader('Expires', '0');

        return $this;
    }

    /**
     * Setzt die Antwort auf "Not Modified" (304)
     */
    public function setNotModified(): self
    {
        $this->setStatusCode(304);
        $this->setBody('');

        return $this;
    }

    /**
     * Sendet die Response
     */
    public function send(): void
    {
        // Verhindere Doppelversand
        if (headers_sent()) {
            return;
        }

        // Sende HTTP-Statuscode
        http_response_code($this->statusCode);

        // Sende HTTP-Header
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        // Sende Cookies
        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                [
                    'expires' => $cookie['expire'],
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httpOnly'],
                    'samesite' => $cookie['sameSite']
                ]
            );
        }

        // Sende Body
        echo $this->body;
    }

    /**
     * Gibt den Statustext für einen Statuscode zurück
     */
    protected function getStatusTextForCode(int $code): string
    {
        $texts = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            103 => 'Early Hints',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        ];

        return $texts[$code] ?? 'Unknown Status';
    }
}
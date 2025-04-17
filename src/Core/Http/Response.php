<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Response-Klasse
 *
 * Repräsentiert eine HTTP-Response
 */
class Response
{
    /**
     * Konstruktor
     *
     * @param string $content Response-Body
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     */
    public function __construct(
        private string $content = '',
        private int    $statusCode = 200,
        private array  $headers = []
    )
    {
        // Standard-Header setzen
        $this->headers = array_merge([
            'Content-Type' => 'text/html; charset=UTF-8'
        ], $this->headers);
    }

    /**
     * Gibt den Response-Body zurück
     *
     * @return string Response-Body
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Setzt den Response-Body
     *
     * @param string $content Response-Body
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Gibt den HTTP-Statuscode zurück
     *
     * @return int HTTP-Statuscode
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Setzt den HTTP-Statuscode
     *
     * @param int $statusCode HTTP-Statuscode
     * @return self
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
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
     * Setzt einen Header
     *
     * @param string $name Name des Headers
     * @param string $value Wert des Headers
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Setzt mehrere Header
     *
     * @param array $headers Header
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Entfernt einen Header
     *
     * @param string $name Name des Headers
     * @return self
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    /**
     * Sendet die Response
     *
     * @return void
     */
    public function send(): void
    {
        // HTTP-Statuscode setzen
        http_response_code($this->statusCode);

        // Header setzen
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        // Content senden
        echo $this->content;
    }

    /**
     * Erstellt eine JSON-Response
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return self
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \Exception('Fehler beim Konvertieren der Daten zu JSON: ' . json_last_error_msg());
        }

        return new self(
            $json,
            $statusCode,
            array_merge([
                'Content-Type' => 'application/json; charset=UTF-8'
            ], $headers)
        );
    }

    /**
     * Erstellt eine HTML-Response
     *
     * @param string $content HTML-Inhalt
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return self
     */
    public static function html(string $content, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            $content,
            $statusCode,
            array_merge([
                'Content-Type' => 'text/html; charset=UTF-8'
            ], $headers)
        );
    }

    /**
     * Erstellt eine Text-Response
     *
     * @param string $content Text-Inhalt
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return self
     */
    public static function text(string $content, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            $content,
            $statusCode,
            array_merge([
                'Content-Type' => 'text/plain; charset=UTF-8'
            ], $headers)
        );
    }

    /**
     * Erstellt eine Redirect-Response
     *
     * @param string $url URL, zu der weitergeleitet werden soll
     * @param int $statusCode HTTP-Statuscode (301 oder 302)
     * @param array $headers HTTP-Header
     * @return self
     */
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): self
    {
        return new self(
            '',
            $statusCode,
            array_merge([
                'Location' => $url
            ], $headers)
        );
    }
}
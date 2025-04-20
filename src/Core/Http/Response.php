<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * API-Optimierte Response-Klasse
 *
 * Fokussiert auf JSON-Responses und API-Kommunikation
 */
class Response
{
    /**
     * Konstruktor
     *
     * @param mixed $content Response-Body
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     */
    public function __construct(
        private mixed $content = '',
        private int   $statusCode = 200,
        private array $headers = []
    )
    {
        // Standardheader für API-Responses
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY'
        ], $this->headers);
    }

    /**
     * Erstellt eine API-Error-Response
     *
     * @param string $message Fehlermeldung
     * @param int $statusCode HTTP-Statuscode
     * @param array $details Zusätzliche Fehlerdetails
     * @return self
     */
    public static function error(
        string $message,
        int    $statusCode = 400,
        array  $details = []
    ): self
    {
        $errorResponse = [
            'error' => true,
            'message' => $message,
            'details' => $details,
            'timestamp' => time()
        ];

        return self::json($errorResponse, $statusCode);
    }

    /**
     * Erstellt eine JSON-Response
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers Zusätzliche HTTP-Header
     * @return self
     */
// src/Core/Http/Response.php
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        // JSON Flag für Sicherheit hinzufügen
        $json = json_encode($data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_THROW_ON_ERROR |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        );

        return new self(
            $json,
            $statusCode,
            array_merge([
                'Content-Type' => 'application/json; charset=UTF-8'
            ], $headers)
        );
    }

    /**
     * Gibt den Response-Inhalt zurück
     *
     * @return mixed Response-Body
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Setzt den Response-Inhalt
     *
     * @param mixed $content Response-Body
     * @return self
     */
    public function setContent(mixed $content): self
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
     * Gibt einen spezifischen Header zurück
     *
     * @param string $name Header-Name
     * @param mixed $default Standardwert
     * @return mixed Header-Wert
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Setzt einen Header
     *
     * @param string $name Header-Name
     * @param string $value Header-Wert
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
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

        // Header senden
        foreach ($this->headers as $name => $value) {
            header("$name: $value", true);
        }

        // Content senden
        echo is_string($this->content) ? $this->content : json_encode($this->content);
    }
}
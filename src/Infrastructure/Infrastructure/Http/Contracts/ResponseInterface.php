<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Contracts;

/**
 * HTTP Response Interface
 */
interface ResponseInterface
{
    /**
     * Setzt den HTTP-Statuscode
     */
    public function setStatusCode(int $statusCode): self;

    /**
     * Gibt den HTTP-Statuscode zurück
     */
    public function getStatusCode(): int;

    /**
     * Gibt den HTTP-Statustext zurück
     */
    public function getStatusText(): string;

    /**
     * Setzt einen HTTP-Header
     */
    public function setHeader(string $name, string $value): self;

    /**
     * Fügt einen HTTP-Header hinzu (läßt mehrere Werte für den gleichen Header zu)
     */
    public function addHeader(string $name, string $value): self;

    /**
     * Entfernt einen HTTP-Header
     */
    public function removeHeader(string $name): self;

    /**
     * Gibt einen HTTP-Header zurück
     */
    public function getHeader(string $name): ?string;

    /**
     * Überprüft, ob ein HTTP-Header gesetzt ist
     */
    public function hasHeader(string $name): bool;

    /**
     * Gibt alle HTTP-Header zurück
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Setzt alle HTTP-Header
     *
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): self;

    /**
     * Setzt den Response-Body
     */
    public function setBody(string $body): self;

    /**
     * Gibt den Response-Body zurück
     */
    public function getBody(): string;

    /**
     * Sendet die Response
     */
    public function send(): void;
}
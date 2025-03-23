<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Contracts;

/**
 * HTTP Request Interface
 */
interface RequestInterface
{
    /**
     * Gibt die HTTP-Methode zurück
     */
    public function getMethod(): string;

    /**
     * Überprüft, ob die aktuelle Anfrage eine bestimmte HTTP-Methode verwendet
     */
    public function isMethod(string $method): bool;

    /**
     * Gibt die URI zurück
     */
    public function getUri(): string;

    /**
     * Gibt den Pfad aus der URI zurück (ohne Query-String)
     */
    public function getPath(): string;

    /**
     * Gibt den Query-String zurück
     */
    public function getQueryString(): ?string;

    /**
     * Gibt alle Query-Parameter zurück
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array;

    /**
     * Gibt einen bestimmten Query-Parameter zurück
     */
    public function getQueryParam(string $name, mixed $default = null): mixed;

    /**
     * Gibt alle Post-Daten zurück
     *
     * @return array<string, mixed>
     */
    public function getPostData(): array;

    /**
     * Gibt einen bestimmten Post-Parameter zurück
     */
    public function getPostParam(string $name, mixed $default = null): mixed;

    /**
     * Gibt ein Input-Parameter zurück (Sucht in GET und POST)
     */
    public function getInput(string $name, mixed $default = null): mixed;

    /**
     * Gibt alle HTTP-Header zurück
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Gibt einen bestimmten Header zurück
     */
    public function getHeader(string $name, mixed $default = null): mixed;

    /**
     * Gibt den Raw-Body zurück
     */
    public function getRawBody(): string;

    /**
     * Gibt die Client-IP-Adresse zurück
     */
    public function getClientIp(): string;
}
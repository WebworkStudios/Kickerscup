<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Contracts;

use App\Infrastructure\Http\Response;

/**
 * Interface für Response Factory
 */
interface ResponseFactoryInterface
{
    /**
     * Erstellt eine neue Standard-Response
     */
    public function create(int $statusCode = 200, ?string $body = null): Response;

    /**
     * Erstellt eine Response mit JSON-Inhalt
     *
     * @param mixed $data Die zu serialisierenden Daten
     */
    public function createJson(mixed $data, int $statusCode = 200, int $options = 0): Response;

    /**
     * Erstellt eine Response mit HTML-Inhalt
     */
    public function createHtml(string $content, int $statusCode = 200): Response;

    /**
     * Erstellt eine Response mit Plain-Text-Inhalt
     */
    public function createText(string $content, int $statusCode = 200): Response;

    /**
     * Erstellt eine Redirect-Response
     */
    public function createRedirect(string $url, int $statusCode = 302): Response;

    /**
     * Erstellt eine Not Found Response (404)
     */
    public function createNotFound(string $message = 'Not Found'): Response;

    /**
     * Erstellt eine Bad Request Response (400)
     */
    public function createBadRequest(string $message = 'Bad Request'): Response;

    /**
     * Erstellt eine Unauthorized Response (401)
     */
    public function createUnauthorized(string $message = 'Unauthorized'): Response;

    /**
     * Erstellt eine Forbidden Response (403)
     */
    public function createForbidden(string $message = 'Forbidden'): Response;

    /**
     * Erstellt eine Internal Server Error Response (500)
     */
    public function createServerError(string $message = 'Internal Server Error'): Response;
}
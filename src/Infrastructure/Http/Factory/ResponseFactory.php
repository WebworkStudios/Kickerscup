<?php


declare(strict_types=1);

namespace App\Infrastructure\Http\Factory;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Response;
use JsonException;

/**
 * Factory für Response-Objekte
 */
#[Injectable]
class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * Erstellt eine neue Standard-Response
     */
    public function create(int $statusCode = 200, ?string $body = null): Response
    {
        return new Response($statusCode, $body);
    }

    /**
     * Erstellt eine Response mit JSON-Inhalt
     *
     * @param mixed $data Die zu serialisierenden Daten
     * @throws JsonException
     */
    public function createJson(mixed $data, int $statusCode = 200, int $options = 0): Response
    {
        $response = $this->create($statusCode);
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data, $options | JSON_THROW_ON_ERROR));

        return $response;
    }

    /**
     * Erstellt eine Response mit HTML-Inhalt
     */
    public function createHtml(string $content, int $statusCode = 200): Response
    {
        $response = $this->create($statusCode);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->setBody($content);

        return $response;
    }

    /**
     * Erstellt eine Response mit Plain-Text-Inhalt
     */
    public function createText(string $content, int $statusCode = 200): Response
    {
        $response = $this->create($statusCode);
        $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->setBody($content);

        return $response;
    }

    /**
     * Erstellt eine Response mit XML-Inhalt
     */
    public function createXml(string $content, int $statusCode = 200): Response
    {
        $response = $this->create($statusCode);
        $response->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->setBody($content);

        return $response;
    }

    /**
     * Erstellt eine Redirect-Response
     */
    public function createRedirect(string $url, int $statusCode = 302): Response
    {
        $response = $this->create($statusCode);
        $response->setHeader('Location', $url);

        return $response;
    }

    /**
     * Erstellt eine Not Found Response (404)
     */
    public function createNotFound(string $message = 'Not Found'): Response
    {
        return $this->create(404, $message);
    }

    /**
     * Erstellt eine Bad Request Response (400)
     */
    public function createBadRequest(string $message = 'Bad Request'): Response
    {
        // JSON-Antwort zurückgeben:
        return $this->createJson([
            'success' => false,
            'error' => $message
        ], 400);
    }

    /**
     * Erstellt eine Unauthorized Response (401)
     */
    public function createUnauthorized(string $message = 'Unauthorized'): Response
    {
        return $this->create(401, $message);
    }

    /**
     * Erstellt eine Forbidden Response (403)
     */
    public function createForbidden(string $message = 'Forbidden'): Response
    {
        return $this->create(403, $message);
    }

    /**
     * Erstellt eine Internal Server Error Response (500)
     */
    public function createServerError(string $message = 'Internal Server Error'): Response
    {
        return $this->create(500, $message);
    }
}
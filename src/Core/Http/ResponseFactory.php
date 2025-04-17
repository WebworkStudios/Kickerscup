<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Factory für Response-Objekte
 */
class ResponseFactory
{
    /**
     * Erstellt eine HTML-Response
     *
     * @param string $content HTML-Inhalt
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function html(string $content, int $statusCode = 200, array $headers = []): Response
    {
        return Response::html($content, $statusCode, $headers);
    }

    /**
     * Erstellt eine Text-Response
     *
     * @param string $content Text-Inhalt
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function text(string $content, int $statusCode = 200, array $headers = []): Response
    {
        return Response::text($content, $statusCode, $headers);
    }

    /**
     * Erstellt eine Redirect-Response
     *
     * @param string $url URL, zu der weitergeleitet werden soll
     * @param int $statusCode HTTP-Statuscode (301 oder 302)
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function redirect(string $url, int $statusCode = 302, array $headers = []): Response
    {
        return Response::redirect($url, $statusCode, $headers);
    }

    /**
     * Erstellt eine 404-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function notFound(string $message = 'Not Found', array $headers = []): Response
    {
        return $this->json([
            'error' => $message
        ], 404, $headers);
    }

    /**
     * Erstellt eine JSON-Response
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function json(mixed $data, int $statusCode = 200, array $headers = []): Response
    {
        return Response::json($data, $statusCode, $headers);
    }

    /**
     * Erstellt eine 400-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function badRequest(string $message = 'Bad Request', array $headers = []): Response
    {
        return $this->json([
            'error' => $message
        ], 400, $headers);
    }

    /**
     * Erstellt eine 401-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function unauthorized(string $message = 'Unauthorized', array $headers = []): Response
    {
        return $this->json([
            'error' => $message
        ], 401, $headers);
    }

    /**
     * Erstellt eine 403-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function forbidden(string $message = 'Forbidden', array $headers = []): Response
    {
        return $this->json([
            'error' => $message
        ], 403, $headers);
    }

    /**
     * Erstellt eine 500-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function serverError(string $message = 'Internal Server Error', array $headers = []): Response
    {
        return $this->json([
            'error' => $message
        ], 500, $headers);
    }

    /**
     * Erstellt eine 204-Response
     *
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function noContent(array $headers = []): Response
    {
        return new Response('', 204, $headers);
    }
}
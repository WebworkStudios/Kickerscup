<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Factory für Response-Objekte
 */
class ResponseFactory
{

    /**
     * Erstellt eine 404-Response
     *
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function notFound(string $message = 'Not Found', array $headers = []): Response
    {
        return $this->error($message, 'NOT_FOUND', [], 404, $headers);
    }

    /**
     * Erstellt eine Fehlerantwort
     *
     * @param string $message Fehlermeldung
     * @param string $errorCode Fehlercode
     * @param array $details Fehlerdetails
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function error(
        string $message,
        string $errorCode = 'ERROR',
        array  $details = [],
        int    $statusCode = 400,
        array  $headers = []
    ): Response
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message
            ]
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        return $this->json($response, $statusCode, $headers);
    }

    /**
     * Erweiterte JSON-Methode mit optionaler Kompression
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @param bool $compress Ob die Response komprimiert werden soll
     * @return Response
     * @throws \JsonException
     */
    public function json(mixed $data, int $statusCode = 200, array $headers = [], bool $compress = false): Response
    {
        $response = Response::json($data, $statusCode, $headers);

        if ($compress) {
            $response->compress();
        }

        return $response;
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
        return $this->error($message, 'BAD_REQUEST', [], 400, $headers);
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
        return $this->error($message, 'UNAUTHORIZED', [], 401, $headers);
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
        return $this->error($message, 'FORBIDDEN', [], 403, $headers);
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
        return $this->error($message, 'SERVER_ERROR', [], 500, $headers);
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

    /**
     * Erstellt eine validierungsfehler-Response
     *
     * @param array $errors Validierungsfehler
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function validationError(
        array  $errors,
        string $message = 'Die Eingabedaten sind ungültig.',
        array  $headers = []
    ): Response
    {
        return $this->error($message, 'VALIDATION_ERROR', $errors, 422, $headers);
    }

    /**
     * Erstellt eine erfolgreiche Antwort auf eine Erstellungsoperation
     *
     * @param mixed $data Erstellte Daten
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function created(mixed $data = null, array $headers = []): Response
    {
        return $this->success($data, 201, $headers);
    }

    /**
     * Erstellt eine Erfolgsantwort
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function success(mixed $data = null, int $statusCode = 200, array $headers = []): Response
    {
        $response = [
            'success' => true
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->json($response, $statusCode, $headers);
    }

    /**
     * Erstellt eine erfolgreiche Antwort auf eine Aktualisierungsoperation
     *
     * @param mixed $data Aktualisierte Daten
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function updated(mixed $data = null, array $headers = []): Response
    {
        return $this->success($data, 200, $headers);
    }

    /**
     * Erstellt eine erfolgreiche Antwort auf eine Löschoperation
     *
     * @param string|null $message Erfolgsmeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function deleted(?string $message = null, array $headers = []): Response
    {
        return $this->json([
            'success' => true,
            'message' => $message ?? 'Die Ressource wurde erfolgreich gelöscht.'
        ], 200, $headers);
    }

    /**
     * Erstellt eine Antwort mit einer Nachricht
     *
     * @param string $message Nachricht
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function message(string $message, int $statusCode = 200, array $headers = []): Response
    {
        return $this->json([
            'success' => true,
            'message' => $message
        ], $statusCode, $headers);
    }

    /**
     * Erstellt eine komprimierte Response
     *
     * @param mixed $data Daten
     * @param int $statusCode HTTP-Statuscode
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function compressed(mixed $data, int $statusCode = 200, array $headers = []): Response
    {
        $response = $this->json($data, $statusCode, $headers);
        return $response->compress();
    }

    /**
     * Erstellt eine Antwort für einen Rate-Limit-Fehler
     *
     * @param int $retryAfter Sekunden bis zum nächsten Versuch
     * @param string $message Fehlermeldung
     * @param array $headers HTTP-Header
     * @return Response
     */
    public function tooManyRequests(
        int    $retryAfter = 60,
        string $message = 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
        array  $headers = []
    ): Response
    {
        $headers['Retry-After'] = (string)$retryAfter;

        return $this->error(
            $message,
            'RATE_LIMIT_EXCEEDED',
            ['retry_after' => $retryAfter],
            429,
            $headers
        );
    }
}
<?php


declare(strict_types=1);

namespace App\Core\Error;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use Throwable;

/**
 * Zentraler Fehlerhandler für API-Anfragen
 */
class ErrorHandler
{
    /**
     * @var ResponseFactory
     */
    private readonly ResponseFactory $responseFactory;

    /**
     * Aktivieren von Debug-Informationen
     */
    private bool $debug;

    /**
     * Konstruktor
     *
     * @param ResponseFactory $responseFactory
     * @param bool $debug Debug-Modus aktivieren
     */
    public function __construct(ResponseFactory $responseFactory, bool $debug = false)
    {
        $this->responseFactory = $responseFactory;
        $this->debug = $debug;
    }

    /**
     * Verarbeitet einen Fehler und gibt eine konsistente API-Antwort zurück
     *
     * @param Throwable $error Der aufgetretene Fehler
     * @param Request $request Der aktuelle Request
     * @return Response Eine API-konforme Fehlerantwort
     */
    public function handleError(Throwable $error, Request $request): Response
    {
        // Fehlertyp bestimmen und entsprechenden HTTP-Status wählen
        $statusCode = $this->determineStatusCode($error);

        // Fehler protokollieren
        $this->logError($error, $request, $statusCode);

        // API-Antwort erstellen
        return $this->createErrorResponse($error, $statusCode);
    }

    /**
     * Bestimmt den HTTP-Statuscode basierend auf dem Fehlertyp
     *
     * @param Throwable $error Der aufgetretene Fehler
     * @return int HTTP-Statuscode
     */
    private function determineStatusCode(Throwable $error): int
    {
        return match (true) {
            $error instanceof ValidationException => 422,
            $error instanceof NotFoundException => 404,
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthoriziationException => 403,
            $error instanceof BadRequestException => 400,
            $error instanceof \App\Core\Database\Exceptions\ConnectionException,
                $error instanceof \App\Core\Database\Exceptions\QueryException => 503,
            default => 500,
        };
    }

    /**
     * @param Throwable $error
     * @param Request $request
     * @param int $statusCode
     * @return void
     * @throws \Exception
     */
    private function logError(Throwable $error, Request $request, int $statusCode): void
    {
        $method = $request->getMethod();
        $uri = $request->getUri();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $logLevel = $statusCode >= 500 ? 'error' : 'warning';
        $context = [
            'exception' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'code' => $error->getCode(),
            'status_code' => $statusCode,
            'ip_address' => $ipAddress,
            'user_agent' => $request->getHeader('User-Agent', 'unknown'),
            // Nur gekürzte Trace in Produktion
            'trace' => $this->debug ? $error->getTraceAsString() : substr($error->getTraceAsString(), 0, 500),
        ];

        app_log("[$method $uri] {$error->getMessage()}", $context, $logLevel);
    }
    /**
     * Erstellt eine Fehlerantwort im API-Format
     *
     * @param Throwable $error Der aufgetretene Fehler
     * @param int $statusCode HTTP-Statuscode
     * @return Response
     */
    private function createErrorResponse(Throwable $error, int $statusCode): Response
    {
        $errorCode = $this->getErrorCode($error);

        $response = [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $this->getErrorMessage($error),
            ]
        ];

        // Debug-Informationen hinzufügen, wenn Debug-Modus aktiv
        if ($this->debug) {
            $response['error']['debug'] = [
                'exception' => get_class($error),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => array_slice(explode("\n", $error->getTraceAsString()), 0, 15)
            ];
        }

        // Validierungsfehler
        if ($error instanceof ValidationException && $error->getErrors()) {
            $response['error']['details'] = $error->getErrors();
        }

        return $this->responseFactory->json($response, $statusCode);
    }

    /**
     * Extrahiert einen Fehlercode aus der Exception
     *
     * @param Throwable $error Der aufgetretene Fehler
     * @return string Fehlercode für API-Antworten
     */
    private function getErrorCode(Throwable $error): string
    {
        // Wenn die Exception einen Errorcode als Methode bereitstellt
        if (method_exists($error, 'getErrorCode')) {
            return $error->getErrorCode();
        }

        // Oder einen Standard-Fehlercode basierend auf der Exceptionklasse
        $className = basename(str_replace('\\', '/', get_class($error)));
        $className = str_replace('Exception', '', $className);

        if (empty($className)) {
            $className = 'Unknown';
        }

        // Camel-Case zu Underscore-Case umwandeln
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Gibt eine benutzerfreundliche Fehlermeldung zurück
     *
     * @param Throwable $error Der aufgetretene Fehler
     * @return string Benutzerfreundliche Fehlermeldung
     */
    private function getErrorMessage(Throwable $error): string
    {
        // Im Produktionsmodus keine internen Fehlermeldungen anzeigen
        if (!$this->debug && $error instanceof \Error) {
            return 'Ein interner Serverfehler ist aufgetreten.';
        }

        // Bei benutzerdefinierten Exceptions immer die Meldung verwenden
        if (
            $error instanceof ValidationException ||
            $error instanceof NotFoundException ||
            $error instanceof AuthenticationException ||
            $error instanceof AuthoriziationException ||
            $error instanceof BadRequestException
        ) {
            return $error->getMessage();
        }

        // Im Produktionsmodus generische Fehler für Datenbankprobleme
        if (!$this->debug && (
                $error instanceof \App\Core\Database\Exceptions\ConnectionException ||
                $error instanceof \App\Core\Database\Exceptions\QueryException
            )) {
            return 'Datenbankfehler aufgetreten. Bitte versuchen Sie es später erneut.';
        }

        // Im Produktionsmodus generische Meldung für alle anderen Fehler
        if (!$this->debug) {
            return 'Ein interner Serverfehler ist aufgetreten.';
        }

        // Im Debug-Modus die tatsächliche Fehlermeldung zurückgeben
        return $error->getMessage();
    }
}
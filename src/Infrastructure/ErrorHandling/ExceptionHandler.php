<?php

declare(strict_types=1);

namespace App\Infrastructure\ErrorHandling;

use App\Infrastructure\Config\Config;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\ErrorHandling\Contracts\ExceptionHandlerInterface;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Logging\LogLevel;
use Error;
use LogicException;
use Throwable;

#[Injectable]
#[Singleton]
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Registrierte Exception-Handler
     *
     * @var array<string, callable>
     */
    protected array $handlers = [];

    /**
     * Registrierte Transformatoren
     *
     * @var array<string, callable>
     */
    protected array $transformers = [];

    /**
     * Registrierte Kontextsammler
     *
     * @var array<string, callable>
     */
    protected array $contextCollectors = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected LoggerInterface   $logger,
        protected ?RequestInterface $request = null
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Throwable $exception, array $context = []): void
    {
        // Transformiere die Exception zuerst
        $exception = $this->transform($exception, $context);

        // Sammle zusätzliche Kontextinformationen
        $context = array_merge($context, $this->collectAdditionalContext($exception));

        // Versuche, einen spezifischen Handler zu finden
        foreach ($this->handlers as $exceptionClass => $handler) {
            if ($exception instanceof $exceptionClass) {
                call_user_func($handler, $exception, $context);
                return;
            }
        }

        // Fallback: Berichte die Exception
        $this->report($exception, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Throwable $exception, array $context = []): Throwable
    {
        foreach ($this->transformers as $exceptionClass => $transformer) {
            if ($exception instanceof $exceptionClass) {
                $result = call_user_func($transformer, $exception, $context);

                // Wenn der Transformer eine Exception zurückgibt, verwende diese
                if ($result instanceof Throwable) {
                    return $result;
                }
            }
        }

        // Wenn keine Transformation erfolgt ist, gib die ursprüngliche Exception zurück
        return $exception;
    }

    /**
     * Sammelt zusätzliche Kontextinformationen
     *
     * @param Throwable $exception Die aufgetretene Exception
     * @return array<string, mixed> Gesammelte Kontextinformationen
     */
    protected function collectAdditionalContext(Throwable $exception): array
    {
        $additionalContext = [];

        // Grundlegende Informationen über die Anwendung
        $additionalContext['environment'] = (new Config)->get('app.env', 'production');
        $additionalContext['php_version'] = PHP_VERSION;
        $additionalContext['memory_usage'] = memory_get_usage(true);

        // Aufruf aller registrierten Kontextsammler
        foreach ($this->contextCollectors as $name => $collector) {
            try {
                $result = call_user_func($collector, $exception);
                if (is_array($result)) {
                    $additionalContext[$name] = $result;
                }
            } catch (Throwable $e) {
                // Fehler im Sammler verhindern nicht die weitere Verarbeitung
                $additionalContext['collector_errors'][$name] = $e->getMessage();
            }
        }

        return $additionalContext;
    }

// src/Infrastructure/ErrorHandling/ExceptionHandler.php
    public function report(Throwable $exception, array $context = []): void
    {
        // Bestimme den Schweregrad der Exception
        $needsFullContext = $exception instanceof Error ||
            $this->isHighSeverityException($exception);

        // Bei weniger kritischen Exceptions minimalen Kontext sammeln für bessere Performance
        if (!$needsFullContext) {
            $minimalContext = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];

            // Request-Informationen hinzufügen, falls verfügbar
            if ($this->request !== null) {
                $minimalContext['request'] = [
                    'method' => $this->request->getMethod(),
                    'path' => $this->request->getPath(),
                    'ip' => $this->request->getClientIp()
                ];
            }

            // Log mit minimalem Kontext
            $this->logger->exception(
                $exception,
                $this->getLogLevelForException($exception)->value,
                '',
                $minimalContext
            );
            return;
        }

        // Vollständiger Kontext für kritische Fehler
        // Erweitere den Kontext mit Request-Informationen
        if ($this->request !== null) {
            $context['request'] = $this->collectRequestInformation($this->request);
        }

        // Füge globale Informationen hinzu
        $context['global'] = $this->collectGlobalInformation();

        // Bestimme das Log-Level basierend auf der Exception-Schwere
        $level = $this->getLogLevelForException($exception);

        // Verwende den Logger, um die Exception zu protokollieren
        $this->logger->exception($exception, $level->value, '', $context);
    }

    /**
     * Prüft, ob eine Exception als hochkritisch eingestuft werden sollte
     *
     * @param Throwable $exception Die zu prüfende Exception
     * @return bool
     */
    private function isHighSeverityException(Throwable $exception): bool
    {
        // Spezifische kritische Exception-Typen
        $highSeverityClasses = [
            'App\\Infrastructure\\Security\\Exceptions\\SecurityException',
            'App\\Infrastructure\\Database\\Exceptions\\ConnectionException',
            'App\\Infrastructure\\Container\\Exceptions\\ContainerException',
        ];

        // Prüfe auf Exception-Typen mit array_any
        if (array_any($highSeverityClasses, fn($class) => $exception instanceof $class)) {
            return true;
        }

        // Prüfe auf kritische Schlüsselwörter in der Nachricht
        $criticalKeywords = ['security breach', 'SQL injection', 'authentication failure'];
        if (array_any($criticalKeywords, fn($keyword) => stripos($exception->getMessage(), $keyword) !== false)) {
            return true;
        }

        // Prüfe auf hohen Fehlercode
        if (method_exists($exception, 'getCode') && $exception->getCode() >= 500) {
            return true;
        }

        return false;
    }

    /**
     * Sammelt detaillierte Informationen über den Request
     *
     * @param RequestInterface $request Der aktuelle Request
     * @return array<string, mixed> Gesammelte Request-Informationen
     */
    protected function collectRequestInformation(RequestInterface $request): array
    {
        $requestInfo = [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'query' => $request->getQueryParams(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->getHeader('user-agent'),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
        ];

        // Füge POST-Daten hinzu, wenn vorhanden (sensible Daten filtern)
        if (!$request->isGet()) {
            $requestInfo['post_data'] = $this->sanitizeData($request->getPostData());
        }

        return $requestInfo;
    }

    /**
     * Entfernt sensible Daten aus Headers
     *
     * @param array<string, string> $headers Die zu bereinigenden Headers
     * @return array<string, string> Bereinigte Headers
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '[REDACTED]';
            }
        }

        return $headers;
    }

    /**
     * Entfernt sensible Daten
     *
     * @param array<string, mixed> $data Die zu bereinigenden Daten
     * @return array<string, mixed> Bereinigte Daten
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'credit_card',
            'card_number',
            'cvv',
            'secret',
            'token',
            'api_key',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }

    /**
     * Sammelt globale Informationen
     *
     * @return array<string, mixed> Gesammelte globale Informationen
     */
    protected function collectGlobalInformation(): array
    {
        return [
            'time' => date('Y-m-d H:i:s'),
            'server' => $this->sanitizeServerData($_SERVER),
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
        ];
    }

    /**
     * Entfernt sensible Daten aus Server-Daten
     *
     * @param array<string, mixed> $serverData Die zu bereinigenden Server-Daten
     * @return array<string, mixed> Bereinigte Server-Daten
     */
    protected function sanitizeServerData(array $serverData): array
    {
        $sensitiveKeys = [
            'PHP_AUTH_PW',
            'HTTP_AUTHORIZATION',
        ];

        $result = [];

        // Nur ausgewählte Informationen einbeziehen
        $includeKeys = [
            'SERVER_NAME',
            'SERVER_ADDR',
            'REQUEST_URI',
            'REQUEST_TIME',
            'SERVER_SOFTWARE',
            'REQUEST_METHOD',
            'HTTP_HOST',
            'HTTPS',
            'REMOTE_ADDR',
            'SCRIPT_FILENAME',
        ];

        foreach ($includeKeys as $key) {
            if (isset($serverData[$key])) {
                $result[$key] = in_array($key, $sensitiveKeys) ? '[REDACTED]' : $serverData[$key];
            }
        }

        return $result;
    }

    /**
     * Bestimmt das geeignete Log-Level für eine Exception
     *
     * @param Throwable $exception Die Exception
     * @return LogLevel Das zu verwendende Log-Level
     */
    protected function getLogLevelForException(Throwable $exception): LogLevel
    {
        // Je nach Exception-Typ ein passendes Log-Level zurückgeben
        return match (true) {
            // Schwerwiegende Fehler
            $exception instanceof Error => LogLevel::CRITICAL,

            // Logische Fehler
            $exception instanceof LogicException => LogLevel::WARNING,

            // Alle anderen Exceptions (inkl. RuntimeException)
            default => LogLevel::ERROR,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function registerTransformer(string $sourceException, callable $transformer): static
    {
        $this->transformers[$sourceException] = $transformer;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContextCollector(callable $collector, ?string $name = null): static
    {
        $key = $name ?? 'collector_' . (count($this->contextCollectors) + 1);
        $this->contextCollectors[$key] = $collector;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerHandler(string $exceptionClass, callable $handler): static
    {
        $this->handlers[$exceptionClass] = $handler;
        return $this;
    }
}
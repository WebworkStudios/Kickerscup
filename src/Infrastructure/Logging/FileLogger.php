<?php


declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use Throwable;

/**
 * Dateibasierter Logger
 */
#[Injectable]
class FileLogger implements LoggerInterface
{
    /**
     * Aktueller Kanal
     */
    protected string $currentChannel;

    /**
     * Konstruktor
     */
    public function __construct(
        protected LoggerConfiguration $config
    )
    {
        $this->currentChannel = $config->defaultChannel;
        $this->ensureLogDirectoryExists();
    }

    /**
     * Stellt sicher, dass das Log-Verzeichnis existiert
     */
    protected function ensureLogDirectoryExists(): void
    {
        $logPath = $this->getBasePath();

        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
    }

    /**
     * Gibt den Basispfad für Logs zurück
     *
     * @return string
     */
    protected function getBasePath(): string
    {
        return rtrim($this->config->logPath, '/\\');
    }

    /**
     * {@inheritdoc}
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $logLevel = LogLevel::fromString($level);
        $channelLevel = LogLevel::fromString($this->getChannelMinLevel());

        // Früher Ausstieg, wenn Log-Level unter dem Minimum
        if (!$logLevel->isAtLeast($channelLevel)) {
            return;
        }

        // Nur einen Pfad-Lookup pro Log-Aufruf durchführen
        $logPath = $this->getChannelLogPath();

        // Formatierung nur durchführen, wenn tatsächlich geloggt wird
        $logEntry = $this->formatLogEntry($level, $message, $context);

        $this->writeLogEntry($logPath, $logEntry);
    }

    /**
     * Gibt das Mindest-Loglevel für den aktuellen Kanal zurück
     *
     * @return string
     */
    protected function getChannelMinLevel(): string
    {
        return $this->config->channels[$this->currentChannel]['level'] ??
            $this->config->channels[$this->config->defaultChannel]['level'];
    }

    /**
     * Gibt den Pfad für die Log-Datei des aktuellen Kanals zurück
     *
     * @return string
     */
    protected function getChannelLogPath(): string
    {
        return $this->config->channels[$this->currentChannel]['path'] ??
            $this->config->channels[$this->config->defaultChannel]['path'];
    }

    /**
     * Formatiert einen Log-Eintrag
     *
     * @param string $level Das Log-Level
     * @param string $message Die Nachricht
     * @param array<string, mixed> $context Der Kontext
     * @return string Der formatierte Log-Eintrag
     */
    protected function formatLogEntry(string $level, string $message, array $context = []): string
    {
        $dateTime = date($this->config->dateFormat);
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $replacements = [
            '{datetime}' => $dateTime,
            '{level}' => strtoupper($level),
            '{message}' => $this->interpolateMessage($message, $context),
            '{context}' => $contextString,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->config->logFormat
        );
    }

    /**
     * Ersetzt Platzhalter in der Nachricht durch Werte aus dem Kontext
     *
     * @param string $message Die Nachricht mit Platzhaltern
     * @param array<string, mixed> $context Der Kontext
     * @return string Die interpolierte Nachricht
     */
    protected function interpolateMessage(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string)$value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Schreibt einen Log-Eintrag in die Datei
     *
     * @param string $path Pfad zur Log-Datei
     * @param string $entry Der Log-Eintrag
     * @return void
     */
    protected function writeLogEntry(string $path, string $entry): void
    {
        $logFile = $this->getBasePath() . DIRECTORY_SEPARATOR . $path;

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            mkdir($directory, permissions: 0755, recursive: true);
        }

        $result = file_put_contents(
            $logFile,
            $entry . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            // Fallback auf PHP-Fehler log, wenn das Schreiben fehlschlägt
            error_log("Konnte nicht in $logFile schreiben: $entry");
        }

        $this->rotateLogFiles($logFile);
    }

    /**
     * Rotiert die Logdateien, wenn nötig
     *
     * @param string $logFile Der Pfad zur Logdatei
     * @return void
     */
    protected function rotateLogFiles(string $logFile): void
    {
        // Früher Ausstieg, wenn keine Rotation nötig
        if (!file_exists($logFile) || filesize($logFile) < 1024 * 1024) {
            return;
        }

        $maxFiles = $this->config->maxFiles;

        // Führe Rotation in einer einzigen Operation durch
        for ($i = $maxFiles; $i >= 1; $i--) {
            $currentFile = $i === 1 ? $logFile : $logFile . '.' . ($i - 1);
            $newFile = $logFile . '.' . $i;

            if ($i === $maxFiles && file_exists($newFile)) {
                unlink($newFile); // Älteste Datei entfernen
            }

            if (file_exists($currentFile)) {
                rename($currentFile, $newFile);
            }
        }

        // Erstelle neue leere Logdatei mit korrekten Berechtigungen
        touch($logFile);
        chmod($logFile, $this->getFilePermissions());
    }

    /**
     * Gibt die Dateiberechtigungen für Logdateien zurück
     *
     * @return int
     */
    protected function getFilePermissions(): int
    {
        if (is_string($this->config->filePermissions)) {
            return intval($this->config->filePermissions, 8);
        }

        return $this->config->filePermissions;
    }

    /**
     * {@inheritdoc}
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY->value, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function exception(Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
    {
        $message = $message ?: $exception->getMessage();

        $exceptionContext = [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        ];

        if ($this->config->logStackTraces) {
            $exceptionContext['exception']['trace'] = $exception->getTraceAsString();
        }

        $this->log($level, $message, array_merge($context, $exceptionContext));
    }

    /**
     * Wechselt zu einem anderen Log-Kanal
     *
     * @param string $channel Der zu verwendende Kanal
     * @return self
     */
    public function channel(string $channel): self
    {
        $newLogger = clone $this;
        $newLogger->currentChannel = $channel;
        return $newLogger;
    }
}
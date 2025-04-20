<?php


declare(strict_types=1);

namespace App\Core\Queue;

use Exception;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Repräsentiert einen Job in der Queue
 */
abstract class Job
{
    /**
     * Eindeutige Job-ID
     */
    private ?string $id = null;

    /**
     * Queue, in der der Job eingeiht wurde
     */
    private string $queue = 'default';

    /**
     * Anzahl der Versuche, den Job auszuführen
     */
    private int $attempts = 0;

    /**
     * Maximale Anzahl der Ausführungsversuche
     */
    private int $maxAttempts = 3;

    /**
     * Zeitstempel der Erstellung
     */
    private int $createdAt;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->createdAt = time();
    }

    /**
     * Abstrakte Methode zur Job-Ausführung
     *
     * @return mixed Rückgabewert der Job-Ausführung
     * @throws Exception Bei Ausführungsfehlern
     */
    abstract public function handle(): mixed;

    /**
     * Setzen der Job-ID
     *
     * @param string $id Eindeutige Job-ID
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Setzen der Queue
     *
     * @param string $queue Name der Queue
     * @return self
     */
    public function setQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Inkrementiert Ausführungsversuche
     *
     * @return void
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * Serialisiert den Job zur Speicherung
     *
     * @return array Serialisierte Job-Daten
     * @throws ReflectionException
     */
    public function serialize(): array
    {
        $reflection = new ReflectionClass($this);
        $data = [
            'class' => $reflection->getName(),
            'id' => $this->id,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'createdAt' => $this->createdAt,
            'properties' => []
        ];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            // Nur serialisierbare Werte speichern
            if (is_scalar($value) || $value === null ||
                (is_object($value) && method_exists($value, '__serialize'))) {
                $data['properties'][$property->getName()] = $value;
            }
        }

        return $data;
    }

    /**
     * Deserialisiert einen Job
     *
     * @param array $data Serialisierte Job-Daten
     * @return static Deserializierter Job
     * @throws ReflectionException
     */
    public static function unserialize(array $data): static
    {
        /** @var static $job */
        $job = new $data['class']();
        $reflection = new ReflectionClass($job);

        foreach ($data['properties'] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($job, $value);
        }

        $job->setId($data['id']);
        $job->setQueue($data['queue']);

        return $job;
    }

    /**
     * Überprüft, ob weitere Ausführungsversuche möglich sind
     *
     * @return bool True, wenn weitere Versuche möglich, sonst false
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * Prüft, ob ein Fehler als "retryable" betrachtet wird
     *
     * @param Throwable $exception Aufgetretene Exception
     * @return bool True, wenn Retry möglich, sonst false
     */
    public function shouldRetry(Throwable $exception): bool
    {
        // Standardimplementierung
        return !$exception instanceof Exception;
    }

    /**
     * Getter für Job-ID
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Getter für Queue-Name
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Getter für Ausführungsversuche
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }
}
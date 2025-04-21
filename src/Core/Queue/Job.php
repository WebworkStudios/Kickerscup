<?php

declare(strict_types=1);

namespace App\Core\Queue;

use Exception;
use Throwable;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Repräsentiert einen Job in der Queue
 *
 * Basisklasse für alle Jobs, die in der Queue verarbeitet werden können.
 * Jobs können verzögert, wiederholt und basierend auf Fehlern unterschiedlich
 * behandelt werden.
 */
abstract class Job
{
    /**
     * Eindeutige Job-ID
     */
    private ?string $id = null;

    /**
     * Queue, in der der Job eingereiht wurde
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
    private readonly int $createdAt;

    /**
     * Tag für Gruppierung oder Filterung
     */
    private ?string $tag = null;

    /**
     * Konstruktor
     *
     * @param string|null $tag Optionaler Tag für Job-Gruppierung
     */
    public function __construct(?string $tag = null)
    {
        $this->createdAt = time();
        $this->tag = $tag;
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
     * Getter für Job-ID
     *
     * @return string|null Die ID des Jobs oder null
     */
    public function getId(): ?string
    {
        return $this->id;
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
     * Getter für Queue-Name
     *
     * @return string Der Name der Queue
     */
    public function getQueue(): string
    {
        return $this->queue;
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
     * Getter für Ausführungsversuche
     *
     * @return int Anzahl der Versuche
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Setzt die maximale Anzahl der Ausführungsversuche
     *
     * @param int $maxAttempts Maximale Anzahl der Versuche
     * @return self
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * Gibt den Tag des Jobs zurück
     *
     * @return string|null Der Tag des Jobs oder null
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * Serialisiert den Job zur Speicherung
     *
     * @return array<string, mixed> Serialisierte Job-Daten
     * @throws ReflectionException Wenn ein Reflektionsfehler auftritt
     */
    public function serialize(): array
    {
        $reflection = new ReflectionClass($this);
        $data = [
            'class' => $reflection->getName(),
            'id' => $this->id,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'maxAttempts' => $this->maxAttempts,
            'createdAt' => $this->createdAt,
            'tag' => $this->tag,
            'properties' => []
        ];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE) as $property) {
            // Nur Job-spezifische Properties serialisieren
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $property->setAccessible(true);
                $value = $property->getValue($this);

                // Nur serialisierbare Werte speichern
                if (is_scalar($value) || $value === null || is_array($value) ||
                    (is_object($value) && (method_exists($value, '__serialize') || $value instanceof \Serializable))) {
                    $data['properties'][$property->getName()] = $value;
                } else {
                    // Warnung loggen bei nicht-serialisierbaren Werten
                    app_log("Nicht-serialisierbarer Wert in Job-Property: " . $property->getName(), [
                        'job_class' => $reflection->getName(),
                        'property' => $property->getName(),
                        'type' => gettype($value)
                    ], 'warning');
                }
            }
        }

        return $data;
    }

    /**
     * Deserialisiert einen Job
     *
     * @param array $data Serialisierte Job-Daten
     * @return static Deserializierter Job
     * @throws ReflectionException Wenn ein Reflektionsfehler auftritt
     */
    public static function unserialize(array $data): static
    {
        /** @var static $job */
        $job = new $data['class']($data['tag'] ?? null);
        $reflection = new ReflectionClass($job);

        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $name => $value) {
                if ($reflection->hasProperty($name)) {
                    $property = $reflection->getProperty($name);
                    $property->setAccessible(true);
                    $property->setValue($job, $value);
                }
            }
        }

        $job->setId($data['id']);
        $job->setQueue($data['queue']);

        if (isset($data['maxAttempts'])) {
            $job->setMaxAttempts($data['maxAttempts']);
        }

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
        // Standardimplementierung - kann von abgeleiteten Klassen überschrieben werden
        return true;
    }

    /**
     * Methode, die vor der Ausführung des Jobs aufgerufen wird
     *
     * @return void
     */
    public function beforeHandle(): void
    {
        // Kann von abgeleiteten Klassen überschrieben werden
    }

    /**
     * Methode, die nach erfolgreicher Ausführung des Jobs aufgerufen wird
     *
     * @param mixed $result Das Ergebnis des Jobs
     * @return void
     */
    public function afterHandle(mixed $result): void
    {
        // Kann von abgeleiteten Klassen überschrieben werden
    }

    /**
     * Methode, die bei einem Fehler während der Ausführung aufgerufen wird
     *
     * @param Throwable $exception Die aufgetretene Exception
     * @return void
     */
    public function onFailure(Throwable $exception): void
    {
        // Kann von abgeleiteten Klassen überschrieben werden
    }
}
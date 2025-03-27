<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

class ValidationException extends \RuntimeException
{
    /**
     * Validierungsfehler
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Erstellt eine neue ValidationException
     *
     * @param string $message Die Fehlermeldung
     * @param int $code Der Fehlercode
     * @param \Throwable|null $previous Die vorherige Exception
     * @param array<string, array<string>> $errors Die Validierungsfehler
     */
    public function __construct(
        string $message = "Validierungsfehler", 
        int $code = 0, 
        ?\Throwable $previous = null,
        array $errors = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Setzt die Validierungsfehler
     *
     * @param array<string, array<string>> $errors Die Validierungsfehler
     * @return $this
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Gibt die Validierungsfehler zurück
     *
     * @return array<string, array<string>> Die Validierungsfehler
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Erstellt eine neue Instanz mit Fehlern
     *
     * @param string $message Die Fehlermeldung
     * @param array<string, array<string>> $errors Die Validierungsfehler
     * @return static
     */
    public static function withErrors(string $message, array $errors): self
    {
        $exception = new self($message);
        $exception->setErrors($errors);
        return $exception;
    }
}

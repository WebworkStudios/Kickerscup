<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing\Exceptions;

/**
 * Wird geworfen, wenn eine HTTP-Methode nicht erlaubt ist
 */
class MethodNotAllowedException extends RoutingException
{
    /**
     * @var array<string> Erlaubte Methoden
     */
    private array $allowedMethods = [];

    /**
     * Setzt die erlaubten Methoden
     *
     * @param array<string> $methods Die erlaubten HTTP-Methoden
     * @return self
     */
    public function setAllowedMethods(array $methods): self
    {
        $this->allowedMethods = $methods;
        return $this;
    }

    /**
     * Gibt die erlaubten Methoden zurück
     *
     * @return array<string> Die erlaubten HTTP-Methoden
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
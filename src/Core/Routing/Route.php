<?php
declare(strict_types=1);

namespace App\Core\Routing;

/**
 * Route-Klasse
 *
 * Repräsentiert eine einzelne Route
 */
class Route
{
    /**
     * Gibt den URI der Route zurück
     *
     * @return string URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Gibt die Aktion der Route zurück
     *
     * @return \Closure|string|array Aktion
     */
    public function getAction(): \Closure|string|array
    {
        return $this->action;
    }

    /**
     * Setzt die Domain der Route
     *
     * @param string $domain Domain
     * @return self
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Gibt die Domain der Route zurück
     *
     * @return string|null Domain oder null, wenn keine gesetzt ist
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Setzt die Parameter der Route
     *
     * @param array $parameters Parameter
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Gibt die Parameter der Route zurück
     *
     * @return array Parameter
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Setzt den Namen der Route
     *
     * @param string $name Name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gibt den Namen der Route zurück
     *
     * @return string|null Name oder null, wenn keiner gesetzt ist
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Domain der Route
     */
    private ?string $domain = null;

    /**
     * Name der Route
     */
    private ?string $name = null;

    /**
     * Parameter der Route
     */
    private array $parameters = [];

    /**
     * Konstruktor
     *
     * @param array $methods HTTP-Methoden
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     */
    public function __construct(
        private readonly array                 $methods,
        private readonly string                $uri,
        private readonly \Closure|string|array $action
    )
    {
    }

    /**
     * Gibt die HTTP-Methoden der Route zurück
     *
     * @return array HTTP-Methoden
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
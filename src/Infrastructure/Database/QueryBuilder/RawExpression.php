<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

/**
 * Repräsentiert einen unbearbeiteten SQL-Ausdruck
 */
class RawExpression
{
    protected string $expression;
    protected array $bindings = [];

    /**
     * Erstellt einen neuen Raw-SQL-Ausdruck
     *
     * @param string $expression Der SQL-Ausdruck
     * @param array $bindings Parameter-Bindungen für den Ausdruck
     */
    public function __construct(string $expression, array $bindings = [])
    {
        $this->expression = $expression;
        $this->bindings = $bindings;
    }

    /**
     * Fügt Parameter hinzu
     *
     * @param array $params Parameter für die Abfrage
     * @return static
     */
    public function addParameters(array $params): static
    {
        $this->bindings = array_merge($this->bindings, $params);
        return $this;
    }

    /**
     * Gibt die Parameter zurück
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->bindings;
    }

    /**
     * Gibt den SQL-String zurück
     *
     * @return string
     */
    public function toSql(): string
    {
        return $this->expression;
    }

    /**
     * Gibt die rohen Bindungen zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Gibt die rohe Expression zurück
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
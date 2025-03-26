<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Contracts\QueryBuilderInterface;

/**
 * Repräsentiert einen unbearbeiteten SQL-Ausdruck
 */
class RawExpression implements QueryBuilderInterface
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
     * {@inheritdoc}
     */
    public function addParameters(array $params): static
    {
        $this->bindings = array_merge($this->bindings, $params);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        return $this->bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        return $this->expression;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): \PDOStatement
    {
        throw new \LogicException('RawExpression kann nicht direkt ausgeführt werden');
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
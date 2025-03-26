<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Contracts\QueryBuilderInterface;

class Subquery implements QueryBuilderInterface
{
    private readonly SelectQueryBuilder $builder;
    private string $alias;
    private ?array $columns = null;

    public function __construct(SelectQueryBuilder $builder, string $alias)
    {
        $this->builder = $builder;
        $this->alias = $alias;
    }

    public function addParameters(array $params): static
    {
        $this->builder->addParameters($params);
        return $this;
    }

    public function getParameters(): array
    {
        return $this->builder->getParameters();
    }

    public function toSql(): string
    {
        return '(' . $this->builder->toSql() . ') AS ' . $this->alias;
    }

    public function execute(): \PDOStatement
    {
        return $this->builder->execute();
    }

    public function getBuilder(): SelectQueryBuilder
    {
        return $this->builder;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function withColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function getColumns(): ?array
    {
        return $this->columns;
    }
}
<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

use App\Core\Database\SubQueryBuilder;

/**
 * WHERE-Klausel für SQL-Abfragen
 */
class WhereClause
{
    /**
     * Bedingungen der WHERE-Klausel
     */
    private array $conditions = [];

    /**
     * Parameter für die Bedingungen
     */
    private array $bindings = [];

    /**
     * Aktuelle Bindungs-ID
     */
    private int $bindingId = 0;

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return void
     */
    public function where(string $column, string $operator, mixed $value): void
    {
        $this->addCondition('AND', $column, $operator, $value);
    }

    /**
     * Fügt eine Bedingung hinzu
     *
     * @param string $type Typ der Verknüpfung (AND oder OR)
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return void
     */
    private function addCondition(string $type, string $column, string $operator, mixed $value): void
    {
        $binding = $this->createBindingName();

        $this->conditions[] = [
            'type' => $type,
            'sql' => "$column $operator :$binding"
        ];

        $this->bindings[$binding] = $value;
    }

    /**
     * Erstellt einen Bindungsnamen
     *
     * @return string
     */
    private function createBindingName(): string
    {
        return 'where_' . (++$this->bindingId);
    }

    /**
     * Fügt eine WHERE-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return void
     */
    public function orWhere(string $column, string $operator, mixed $value): void
    {
        $this->addCondition('OR', $column, $operator, $value);
    }

    /**
     * Fügt eine WHERE IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return void
     */
    public function whereIn(string $column, array $values): void
    {
        $this->addInCondition('AND', $column, $values, 'IN');
    }

    /**
     * Fügt eine IN-Bedingung hinzu
     *
     * @param string $type Typ der Verknüpfung (AND oder OR)
     * @param string $column Spalte
     * @param array $values Werte
     * @param string $operator Operator (IN oder NOT IN)
     * @return void
     */
    private function addInCondition(string $type, string $column, array $values, string $operator): void
    {
        if (empty($values)) {
            if ($operator === 'IN') {
                $this->conditions[] = [
                    'type' => $type,
                    'sql' => '0 = 1' // Immer falsch für leere IN-Klausel
                ];
            } else {
                $this->conditions[] = [
                    'type' => $type,
                    'sql' => '1 = 1' // Immer wahr für leere NOT IN-Klausel
                ];
            }
            return;
        }

        $bindings = [];
        foreach ($values as $value) {
            $binding = $this->createBindingName();
            $bindings[] = ":$binding";
            $this->bindings[$binding] = $value;
        }

        $this->conditions[] = [
            'type' => $type,
            'sql' => "$column $operator (" . implode(', ', $bindings) . ")"
        ];
    }

    /**
     * Fügt eine WHERE IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return void
     */
    public function orWhereIn(string $column, array $values): void
    {
        $this->addInCondition('OR', $column, $values, 'IN');
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return void
     */
    public function whereNotIn(string $column, array $values): void
    {
        $this->addInCondition('AND', $column, $values, 'NOT IN');
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return void
     */
    public function orWhereNotIn(string $column, array $values): void
    {
        $this->addInCondition('OR', $column, $values, 'NOT IN');
    }

    /**
     * Fügt eine WHERE NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return void
     */
    public function whereNull(string $column): void
    {
        $this->conditions[] = [
            'type' => 'AND',
            'sql' => "$column IS NULL"
        ];
    }

    /**
     * Fügt eine WHERE NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return void
     */
    public function orWhereNull(string $column): void
    {
        $this->conditions[] = [
            'type' => 'OR',
            'sql' => "$column IS NULL"
        ];
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return void
     */
    public function whereNotNull(string $column): void
    {
        $this->conditions[] = [
            'type' => 'AND',
            'sql' => "$column IS NOT NULL"
        ];
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return void
     */
    public function orWhereNotNull(string $column): void
    {
        $this->conditions[] = [
            'type' => 'OR',
            'sql' => "$column IS NOT NULL"
        ];
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return void
     */
    public function whereBetween(string $column, mixed $min, mixed $max): void
    {
        $minBinding = $this->createBindingName();
        $maxBinding = $this->createBindingName();

        $this->conditions[] = [
            'type' => 'AND',
            'sql' => "$column BETWEEN :$minBinding AND :$maxBinding"
        ];

        $this->bindings[$minBinding] = $min;
        $this->bindings[$maxBinding] = $max;
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return void
     */
    public function orWhereBetween(string $column, mixed $min, mixed $max): void
    {
        $minBinding = $this->createBindingName();
        $maxBinding = $this->createBindingName();

        $this->conditions[] = [
            'type' => 'OR',
            'sql' => "$column BETWEEN :$minBinding AND :$maxBinding"
        ];

        $this->bindings[$minBinding] = $min;
        $this->bindings[$maxBinding] = $max;
    }

    /**
     * Fügt eine WHERE-Bedingung mit einer Subquery hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param SubQueryBuilder $subQuery Unterabfrage
     * @return void
     */
    public function whereSubQuery(string $column, string $operator, SubQueryBuilder $subQuery): void
    {
        $sql = "$column $operator (" . $subQuery->toSql() . ")";

        $this->conditions[] = [
            'type' => 'AND',
            'sql' => $sql
        ];

        $this->bindings = array_merge($this->bindings, $subQuery->getBindings());
    }

    /**
     * Generiert die SQL-Abfrage für die WHERE-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        $sql = 'WHERE ';
        $isFirst = true;

        foreach ($this->conditions as $condition) {
            if ($condition['sql'] === '(') {
                $sql .= ($isFirst ? '' : " {$condition['type']} ") . '(';
                $isFirst = true;
            } elseif ($condition['sql'] === ')') {
                $sql .= ')';
                $isFirst = false;
            } else {
                $sql .= ($isFirst ? '' : " {$condition['type']} ") . $condition['sql'];
                $isFirst = false;
            }
        }

        return $sql;
    }

    /**
     * Gibt die Parameter für die Abfrage zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Fügt eine WHERE-Bedingung mit einer Subquery und OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param SubQueryBuilder $subQuery Unterabfrage
     * @return void
     */
    public function orWhereSubQuery(string $column, string $operator, SubQueryBuilder $subQuery): void
    {
        $sql = "$column $operator (" . $subQuery->toSql() . ")";

        $this->conditions[] = [
            'type' => 'OR',
            'sql' => $sql
        ];

        $this->bindings = array_merge($this->bindings, $subQuery->getBindings());
    }

    /**
     * Startet eine gruppierte Bedingung
     *
     * @return void
     */
    public function beginGroup(): void
    {
        $this->conditions[] = [
            'type' => 'AND',
            'sql' => '('
        ];
    }

    /**
     * Startet eine gruppierte Bedingung mit OR
     *
     * @return void
     */
    public function beginOrGroup(): void
    {
        $this->conditions[] = [
            'type' => 'OR',
            'sql' => '('
        ];
    }

    /**
     * Beendet eine gruppierte Bedingung
     *
     * @return void
     */
    public function endGroup(): void
    {
        $this->conditions[] = [
            'type' => 'END',
            'sql' => ')'
        ];
    }

    /**
     * Prüft, ob Bedingungen vorhanden sind
     *
     * @return bool
     */
    public function hasConditions(): bool
    {
        return array_any($this->conditions, fn($condition) => true);
    }
}
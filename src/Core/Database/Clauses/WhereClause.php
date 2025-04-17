<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * WHERE-Klausel für SQL-Abfragen
 */
class WhereClause implements ClauseInterface
{
    /**
     * WHERE-Bedingungen
     */
    private array $wheres = [];

    /**
     * Parameter für die Abfrage
     */
    private array $params = [];

    /**
     * Standardoperator für WHERE-Bedingungen
     */
    private string $defaultBoolean = 'AND';

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function where(string $column, string $operator, mixed $value, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Fügt eine WHERE IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function whereIn(string $column, array $values, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function whereNotIn(string $column, array $values, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * Fügt eine WHERE NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function whereNull(string $column, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function whereNotNull(string $column, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'notNull',
            'column' => $column,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max, ?string $boolean = null): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return self
     */
    public function orWhereBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->whereBetween($column, $min, $max, 'OR');
    }

    /**
     * Prüft, ob WHERE-Bedingungen vorhanden sind
     *
     * @return bool
     */
    public function hasConditions(): bool
    {
        return !empty($this->wheres);
    }

    /**
     * Generiert die SQL für die WHERE-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = 'WHERE ';
        $clauses = [];

        foreach ($this->wheres as $i => $where) {
            $prefix = $i === 0 ? '' : " {$where['boolean']} ";

            match ($where['type']) {
                'basic' => $this->buildBasicWhere($clauses, $i, $prefix, $where),
                'in' => $this->buildInWhere($clauses, $i, $prefix, $where),
                'notIn' => $this->buildNotInWhere($clauses, $i, $prefix, $where),
                'null' => $this->buildNullWhere($clauses, $prefix, $where),
                'notNull' => $this->buildNotNullWhere($clauses, $prefix, $where),
                'between' => $this->buildBetweenWhere($clauses, $i, $prefix, $where),
                default => null
            };
        }

        return $sql . implode(' ', $clauses);
    }

    /**
     * Baut eine einfache WHERE-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param int $i Index
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildBasicWhere(array &$clauses, int $i, string $prefix, array $where): void
    {
        $key = "where_{$i}";
        $clauses[] = "{$prefix}{$where['column']} {$where['operator']} :{$key}";
        $this->params[$key] = $where['value'];
    }

    /**
     * Baut eine WHERE IN-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param int $i Index
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildInWhere(array &$clauses, int $i, string $prefix, array $where): void
    {
        if (empty($where['values'])) {
            $clauses[] = "{$prefix}1 = 0"; // Immer falsch, wenn leeres IN
            return;
        }

        $placeholders = [];
        foreach ($where['values'] as $j => $value) {
            $key = "where_{$i}_{$j}";
            $placeholders[] = ":{$key}";
            $this->params[$key] = $value;
        }

        $clauses[] = "{$prefix}{$where['column']} IN (" . implode(', ', $placeholders) . ")";
    }

    /**
     * Baut eine WHERE NOT IN-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param int $i Index
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildNotInWhere(array &$clauses, int $i, string $prefix, array $where): void
    {
        if (empty($where['values'])) {
            $clauses[] = "{$prefix}1 = 1"; // Immer wahr, wenn leeres NOT IN
            return;
        }

        $placeholders = [];
        foreach ($where['values'] as $j => $value) {
            $key = "where_{$i}_{$j}";
            $placeholders[] = ":{$key}";
            $this->params[$key] = $value;
        }

        $clauses[] = "{$prefix}{$where['column']} NOT IN (" . implode(', ', $placeholders) . ")";
    }

    /**
     * Baut eine WHERE NULL-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildNullWhere(array &$clauses, string $prefix, array $where): void
    {
        $clauses[] = "{$prefix}{$where['column']} IS NULL";
    }

    /**
     * Baut eine WHERE NOT NULL-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildNotNullWhere(array &$clauses, string $prefix, array $where): void
    {
        $clauses[] = "{$prefix}{$where['column']} IS NOT NULL";
    }

    /**
     * Baut eine WHERE BETWEEN-Bedingung
     *
     * @param array &$clauses Referenz zu den SQL-Klauseln
     * @param int $i Index
     * @param string $prefix Präfix (AND/OR)
     * @param array $where WHERE-Definition
     * @return void
     */
    private function buildBetweenWhere(array &$clauses, int $i, string $prefix, array $where): void
    {
        $minKey = "where_{$i}_min";
        $maxKey = "where_{$i}_max";
        $clauses[] = "{$prefix}{$where['column']} BETWEEN :{$minKey} AND :{$maxKey}";
        $this->params[$minKey] = $where['min'];
        $this->params[$maxKey] = $where['max'];
    }

    /**
     * Gibt alle Parameter für die Klausel zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->params;
    }

    /**
     * Setzt den Standard-Booleanschen Operator
     *
     * @param string $operator Der Operator ('AND' oder 'OR')
     * @return self
     */
    public function setDefaultBoolean(string $operator): self
    {
        $this->defaultBoolean = strtoupper($operator) === 'OR' ? 'OR' : 'AND';
        return $this;
    }
}
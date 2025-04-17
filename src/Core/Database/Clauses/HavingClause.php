<?php


declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * HAVING-Klausel für SQL-Abfragen
 */
class HavingClause implements ClauseInterface
{
    /**
     * HAVING-Bedingungen
     */
    private array $havings = [];

    /**
     * Parameter für die Abfrage
     */
    private array $params = [];

    /**
     * Standardoperator für HAVING-Bedingungen
     */
    private string $defaultBoolean = 'AND';

    /**
     * Fügt eine HAVING-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @param string|null $boolean Logischer Operator (AND/OR)
     * @return self
     */
    public function having(string $column, string $operator, mixed $value, ?string $boolean = null): self
    {
        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean ?? $this->defaultBoolean,
        ];

        return $this;
    }

    /**
     * Fügt eine HAVING-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function orHaving(string $column, string $operator, mixed $value): self
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    /**
     * Prüft, ob HAVING-Bedingungen vorhanden sind
     *
     * @return bool
     */
    public function hasConditions(): bool
    {
        return !empty($this->havings);
    }

    /**
     * Generiert die SQL für die HAVING-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = 'HAVING ';
        $clauses = [];

        foreach ($this->havings as $i => $having) {
            $prefix = $i === 0 ? '' : " {$having['boolean']} ";

            if ($having['type'] === 'basic') {
                $key = "having_{$i}";
                $clauses[] = "{$prefix}{$having['column']} {$having['operator']} :{$key}";
                $this->params[$key] = $having['value'];
            }
        }

        return $sql . implode(' ', $clauses);
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
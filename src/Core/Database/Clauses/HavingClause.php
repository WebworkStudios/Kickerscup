<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * HAVING-Klausel für SQL-Abfragen
 */
class HavingClause
{
    /**
     * Bedingungen der HAVING-Klausel
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
     * Fügt eine HAVING-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return void
     */
    public function having(string $column, string $operator, mixed $value): void
    {
        $this->addCondition('AND', $column, $operator, $value);
    }

    /**
     * Fügt eine HAVING-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return void
     */
    public function orHaving(string $column, string $operator, mixed $value): void
    {
        $this->addCondition('OR', $column, $operator, $value);
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
        return 'having_' . (++$this->bindingId);
    }

    /**
     * Generiert die SQL-Abfrage für die HAVING-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        $sql = 'HAVING ';
        $isFirst = true;

        foreach ($this->conditions as $condition) {
            $sql .= ($isFirst ? '' : " {$condition['type']} ") . $condition['sql'];
            $isFirst = false;
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
}
<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

/**
 * Trait für JSON-Operationen im Query Builder für MySQL
 */
trait JsonQueryTrait
{
    /**
     * Fügt eine WHERE-Bedingung für JSON-Pfad hinzu
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad (z.B. '$.name', '$.addresses[0].city')
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJson(string $column, string $path, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Wenn nur drei Parameter angegeben wurden, verwende = als Operator
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('json');
        $this->parameters[$paramName] = $value;

        // MySQL-spezifischer JSON-Extraktor
        $jsonExpression = "JSON_EXTRACT({$column}, '{$path}')";

        $rawExpr = $this->raw("{$jsonExpression} {$operator} :{$paramName}");

        if ($boolean === 'AND') {
            return $this->where($rawExpr);
        } else {
            return $this->orWhere($rawExpr);
        }
    }

    /**
     * Erstellt eine neue Raw-SQL-Expression
     *
     * @param string $expression Die rohe SQL-Expression
     * @param array $bindings Parameter-Bindungen für die Expression
     * @return RawExpression
     */
    protected function raw(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    /**
     * Fügt eine WHERE OR-Bedingung für JSON-Pfad hinzu
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return static
     */
    public function orWhereJson(string $column, string $path, mixed $operator = null, mixed $value = null): static
    {
        return $this->whereJson($column, $path, $operator, $value, 'OR');
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu, die prüft, ob ein JSON-Pfad existiert
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJsonContains(string $column, string $path, string $boolean = 'AND'): static
    {
        // MySQL-spezifischer JSON-Path-Existenz-Prüfer
        $jsonExistsExpression = "JSON_CONTAINS_PATH({$column}, 'one', '{$path}')";

        return $this->where(
            $this->raw($jsonExistsExpression),
            $boolean
        );
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu, die prüft, ob ein JSON-Pfad existiert
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad
     * @return static
     */
    public function orWhereJsonContains(string $column, string $path): static
    {
        return $this->whereJsonContains($column, $path, 'OR');
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu, die prüft, ob ein JSON-Array einen Wert enthält
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad zum Array
     * @param mixed $value Zu suchender Wert
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJsonArrayContains(string $column, string $path, mixed $value, string $boolean = 'AND'): static
    {
        $paramName = $this->createParameterName('jsonarr');
        $this->parameters[$paramName] = $value;

        // MySQL-spezifischer JSON-Array-Contains-Operator
        $jsonArrayContainsExpression = "JSON_CONTAINS({$column}, CAST(:{$paramName} AS JSON), '{$path}')";

        return $this->where(
            $this->raw($jsonArrayContainsExpression),
            $boolean
        );
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu, die prüft, ob ein JSON-Array einen Wert enthält
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad zum Array
     * @param mixed $value Zu suchender Wert
     * @return static
     */
    public function orWhereJsonArrayContains(string $column, string $path, mixed $value): static
    {
        return $this->whereJsonArrayContains($column, $path, $value, 'OR');
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu, die die Länge eines JSON-Arrays prüft
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad zum Array
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJsonLength(string $column, string $path, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Wenn nur drei Parameter angegeben wurden, verwende = als Operator
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('jsonlen');
        $this->parameters[$paramName] = $value;

        // MySQL-spezifischer JSON-Length-Operator
        $jsonLengthExpression = "JSON_LENGTH({$column}, '{$path}')";

        return $this->where(
            $this->raw("{$jsonLengthExpression} {$operator} :{$paramName}"),
            $boolean
        );
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu, die die Länge eines JSON-Arrays prüft
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad zum Array
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return static
     */
    public function orWhereJsonLength(string $column, string $path, mixed $operator = null, mixed $value = null): static
    {
        return $this->whereJsonLength($column, $path, $operator, $value, 'OR');
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu, die einen JSON-Wert als String vergleicht,
     * dies ist nützlich, wenn MySQL JSON-Werte als JSON-Strings zurückgibt (mit Anführungszeichen)
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJsonText(string $column, string $path, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Wenn nur drei Parameter angegeben wurden, verwende = als Operator
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('jsontext');
        $this->parameters[$paramName] = $value;

        // MySQL-spezifischer JSON_UNQUOTE für String-Vergleiche
        $jsonTextExpression = "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";

        return $this->where(
            $this->raw("{$jsonTextExpression} {$operator} :{$paramName}"),
            $boolean
        );
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu, die einen JSON-Wert als String vergleicht
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $path JSON-Pfad
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return static
     */
    public function orWhereJsonText(string $column, string $path, mixed $operator = null, mixed $value = null): static
    {
        return $this->whereJsonText($column, $path, $operator, $value, 'OR');
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu, die prüft, ob ein JSON-Schlüssel existiert
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $key Der zu prüfende Schlüssel
     * @param string $boolean AND oder OR
     * @return static
     */
    public function whereJsonHasKey(string $column, string $key, string $boolean = 'AND'): static
    {
        // Erstelle den Pfad für den Schlüssel
        $path = '$.' . trim($key, '$.');

        return $this->whereJsonContains($column, $path, $boolean);
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu, die prüft, ob ein JSON-Schlüssel existiert
     *
     * @param string $column Spalte mit JSON-Daten
     * @param string $key Der zu prüfende Schlüssel
     * @return static
     */
    public function orWhereJsonHasKey(string $column, string $key): static
    {
        return $this->whereJsonHasKey($column, $key, 'OR');
    }
}
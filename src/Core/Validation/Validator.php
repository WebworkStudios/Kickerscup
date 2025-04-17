<?php

declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Database\DatabaseManager;

/**
 * Validator für Eingabedaten
 */
class Validator
{
    /**
     * Verfügbare Validierungsregeln
     */
    private array $rules = [
        'required', 'string', 'email', 'numeric', 'integer', 'boolean',
        'min', 'max', 'between', 'in', 'not_in', 'date', 'url',
        'alpha', 'alpha_num', 'alpha_dash', 'regex', 'unique', 'exists'
    ];

    /**
     * Fehlermeldungen für Validierungsregeln
     */
    private array $messages = [
        'required' => 'Das Feld :attribute ist erforderlich.',
        'string' => 'Das Feld :attribute muss ein String sein.',
        'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
        'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
        'integer' => 'Das Feld :attribute muss eine Ganzzahl sein.',
        'boolean' => 'Das Feld :attribute muss einen Wahrheitswert darstellen.',
        'min' => 'Das Feld :attribute muss mindestens :min Zeichen haben.',
        'max' => 'Das Feld :attribute darf maximal :max Zeichen haben.',
        'between' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'in' => 'Der ausgewählte Wert für :attribute ist ungültig.',
        'not_in' => 'Der ausgewählte Wert für :attribute ist ungültig.',
        'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
        'url' => 'Das Feld :attribute muss eine gültige URL sein.',
        'alpha' => 'Das Feld :attribute darf nur Buchstaben enthalten.',
        'alpha_num' => 'Das Feld :attribute darf nur Buchstaben und Zahlen enthalten.',
        'alpha_dash' => 'Das Feld :attribute darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
        'regex' => 'Das Format des Feldes :attribute ist ungültig.',
        'unique' => 'Der Wert für :attribute wird bereits verwendet.',
        'exists' => 'Der ausgewählte Wert für :attribute ist ungültig.'
    ];

    /**
     * Konstruktor
     *
     * @param DatabaseManager|null $db Datenbankmanager für DB-basierte Validierungen
     */
    public function __construct(
        private readonly ?DatabaseManager $db = null
    )
    {
    }

    /**
     * Validiert Daten
     *
     * @param array $data Zu validierende Daten
     * @param array $rules Validierungsregeln
     * @param array $messages Benutzerdefinierte Fehlermeldungen
     * @return ValidationResult
     */
    public function validate(array $data, array $rules, array $messages = []): ValidationResult
    {
        $errors = [];
        $validated = [];

        // Regeln verarbeiten
        foreach ($rules as $field => $fieldRules) {
            // Wenn die Regeln als String übergeben wurden, in ein Array umwandeln
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            // Wert aus den Daten holen
            $value = $data[$field] ?? null;
            $isFieldValid = true;

            // Jede Regel prüfen
            foreach ($fieldRules as $rule) {
                // Parameter aus der Regel extrahieren
                $parameters = [];

                if (is_string($rule)) {
                    if (str_contains($rule, ':')) {
                        [$rule, $paramStr] = explode(':', $rule, 2);
                        $parameters = explode(',', $paramStr);
                    }
                } else if (is_array($rule)) {
                    $parameters = array_slice($rule, 1);
                    $rule = $rule[0];
                }

                // Methode für die Regel bestimmen
                $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

                // Wenn die Methode existiert, die Validierung durchführen
                if (method_exists($this, $method)) {
                    $result = $this->$method($field, $value, $parameters, $data);

                    if ($result !== true) {
                        // Fehlermeldung bestimmen
                        $message = $messages["$field.$rule"]
                            ?? $messages[$rule]
                            ?? $this->messages[$rule]
                            ?? "Validation failed for $field with rule $rule.";

                        // Parameter in der Fehlermeldung ersetzen
                        $message = str_replace(':attribute', $field, $message);

                        foreach ($parameters as $i => $parameter) {
                            $message = str_replace(":$i", $parameter, $message);

                            // Benannte Parameter ersetzen
                            $paramName = ['min', 'max', 'value', 'other'][$i] ?? "param$i";
                            $message = str_replace(":$paramName", $parameter, $message);
                        }

                        $errors[$field][] = $message;
                        $isFieldValid = false;
                        break; // Bei einem Fehler weitere Regeln für dieses Feld überspringen
                    }
                }
            }

            // Wenn keine Fehler für dieses Feld vorhanden sind, zu den validierten Daten hinzufügen
            if ($isFieldValid) {
                $validated[$field] = $value;
            }
        }

        return new ValidationResult($validated, $errors);
    }

    /**
     * Validiert, ob ein Wert vorhanden ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateRequired(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Validiert, ob ein Wert ein String ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateString(string $field, mixed $value, array $parameters, array $data): bool
    {
        return is_string($value);
    }

    /**
     * Validiert, ob ein Wert eine E-Mail-Adresse ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateEmail(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validiert, ob ein Wert eine Zahl ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateNumeric(string $field, mixed $value, array $parameters, array $data): bool
    {
        return is_numeric($value);
    }

    /**
     * Validiert, ob ein Wert eine Ganzzahl ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateInteger(string $field, mixed $value, array $parameters, array $data): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validiert, ob ein Wert ein Boolean ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateBoolean(string $field, mixed $value, array $parameters, array $data): bool
    {
        $acceptable = [true, false, 0, 1, '0', '1'];

        return in_array($value, $acceptable, strict: true);
    }

    /**
     * Validiert, ob ein Wert eine minimale Länge hat
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateMin(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (empty($parameters)) {
            return false;
        }

        $min = (int)$parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Validiert, ob ein Wert eine maximale Länge hat
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateMax(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (empty($parameters)) {
            return false;
        }

        $max = (int)$parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Validiert, ob ein Wert zwischen zwei Werten liegt
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateBetween(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (count($parameters) < 2) {
            return false;
        }

        $min = (int)$parameters[0];
        $max = (int)$parameters[1];

        return $this->validateMin($field, $value, [$min], $data) &&
            $this->validateMax($field, $value, [$max], $data);
    }

    /**
     * Validiert, ob ein Wert in einer Liste von Werten enthalten ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateIn(string $field, mixed $value, array $parameters, array $data): bool
    {
        return in_array($value, $parameters, strict: true);
    }

    /**
     * Validiert, ob ein Wert nicht in einer Liste von Werten enthalten ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateNotIn(string $field, mixed $value, array $parameters, array $data): bool
    {
        return !array_any($parameters, fn($item) => $item === $value);
    }

    /**
     * Validiert, ob ein Wert ein gültiges Datum ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateDate(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $format = $parameters[0] ?? null;

        if ($format) {
            $date = \DateTime::createFromFormat($format, $value);
            return $date && $date->format($format) === $value;
        }

        return strtotime($value) !== false;
    }

    /**
     * Validiert, ob ein Wert eine gültige URL ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateUrl(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validiert, ob ein Wert nur Buchstaben enthält
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateAlpha(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM]+$/u', $value) === 1;
    }

    /**
     * Validiert, ob ein Wert nur Buchstaben und Zahlen enthält
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateAlphaNum(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN]+$/u', $value) === 1;
    }

    /**
     * Validiert, ob ein Wert nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthält
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateAlphaDash(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN_-]+$/u', $value) === 1;
    }

    /**
     * Validiert, ob ein Wert einem regulären Ausdruck entspricht
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateRegex(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        $pattern = $parameters[0];

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validiert, ob ein Wert einzigartig in der Datenbank ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateUnique(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($this->db === null || count($parameters) < 1) {
            return false;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $exceptId = $parameters[2] ?? null;
        $idColumn = $parameters[3] ?? 'id';

        $query = $this->db->table($table)->where($column, '=', $value);

        if ($exceptId !== null) {
            $query->where($idColumn, '!=', $exceptId);
        }

        return $query->count() === 0;
    }

    /**
     * Validiert, ob ein Wert in der Datenbank existiert
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateExists(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($this->db === null || count($parameters) < 1) {
            return false;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $field;

        return $this->db->table($table)->where($column, '=', $value)->count() > 0;
    }
}
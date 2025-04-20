<?php

declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Database\DatabaseManager;
use App\Core\Translation\Translator;

/**
 * Validator für Eingabedaten mit PHP 8.4 Features
 */
class Validator
{
    /**
     * Fehlermeldungen für Validierungsregeln
     */
    private array $messages;

    /**
     * Konstruktor
     *
     * @param ?Translator $translator Translator-Instanz
     * @param DatabaseManager|null $db Datenbankmanager für DB-basierte Validierungen
     */
    public function __construct(
        private readonly ?Translator      $translator = null,
        private readonly ?DatabaseManager $db = null
    )
    {
        // Standard-Fehlermeldungen auf Englisch
        // (werden nur verwendet, wenn keine Übersetzungen gefunden werden)
        $this->messages = [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute field must be a string.',
            'email' => 'The :attribute field must be a valid email address.',
            'numeric' => 'The :attribute field must be a number.',
            'integer' => 'The :attribute field must be an integer.',
            'boolean' => 'The :attribute field must be a boolean.',
            'min' => 'The :attribute field must be at least :min characters.',
            'max' => 'The :attribute field may not be greater than :max characters.',
            'between' => 'The :attribute field must be between :min and :max.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'date' => 'The :attribute field must be a valid date.',
            'url' => 'The :attribute field must be a valid URL.',
            'alpha' => 'The :attribute field may only contain letters.',
            'alpha_num' => 'The :attribute field may only contain letters and numbers.',
            'alpha_dash' => 'The :attribute field may only contain letters, numbers, dashes and underscores.',
            'regex' => 'The :attribute field format is invalid.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'enum' => 'The selected :attribute is not a valid option.'
        ];
    }

    /**
     * Gibt die verfügbaren Validierungsregeln zurück
     *
     * @return array Liste der verfügbaren Validierungsregeln
     */
    public function getAvailableRules(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Validiert Daten und wirft bei Fehler eine ValidationException
     *
     * @param array $data Zu validierende Daten
     * @param array $rules Validierungsregeln
     * @param array $messages Benutzerdefinierte Fehlermeldungen
     * @return array Validierte Daten
     * @throws \App\Core\Error\ValidationException Wenn die Validierung fehlschlägt
     */
    public function validateOrFail(array $data, array $rules, array $messages = []): array
    {
        $result = $this->validate($data, $rules, $messages);

        if ($result->fails()) {
            throw new \App\Core\Error\ValidationException(
                'Die Eingabedaten sind ungültig.',
                $result->errors()
            );
        }

        return $result->validated();
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

        // Translator-Instanz für diese Methode holen
        $translator = $this->getTranslator();

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
                [$ruleName, $parameters] = $this->parseRule($rule);

                // Methode für die Regel bestimmen
                $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));

                // Wenn die Methode existiert, die Validierung durchführen
                if (method_exists($this, $method)) {
                    $result = $this->$method($field, $value, $parameters, $data);

                    if ($result !== true) {
                        // Übersetzung für die Fehlermeldung holen
                        $translationKey = "validation.$ruleName";

                        // Fehlermeldung bestimmen (Priorität: benutzerdefiniert > übersetzt > Standard)
                        $message = $messages["$field.$ruleName"] ?? $messages[$ruleName] ?? null;

                        if ($message === null && $translator->has($translationKey)) {
                            $message = $translator->get($translationKey);
                        } else {
                            $message = $message ?? $this->messages[$ruleName] ?? "Validation failed for $field with rule $ruleName.";
                        }

                        // Parameter in der Fehlermeldung ersetzen
                        $replace = ['attribute' => $field];

                        foreach ($parameters as $i => $parameter) {
                            $replace[$i] = $parameter;

                            // Benannte Parameter ersetzen
                            $paramName = match($i) {
                                0 => 'min',
                                1 => 'max',
                                2 => 'value',
                                3 => 'other',
                                default => "param$i"
                            };
                            $replace[$paramName] = $parameter;
                        }

                        $message = $translator->replaceParameters($message, $replace);

                        $errors[$field][] = $message;
                        $isFieldValid = false;
                        break; // Bei einem Fehler weitere Regeln für dieses Feld überspringen
                    }
                }
            }

            // Wenn keine Fehler für dieses Feld vorhanden sind, zu den validierten Daten hinzufügen
            if ($isFieldValid && isset($data[$field])) {
                $validated[$field] = $value;
            }
        }

        return new ValidationResult($validated, $errors);
    }

    /**
     * Liefert die Translator-Instanz oder erstellt eine neue wenn nötig
     *
     * @return Translator
     */
    private function getTranslator(): Translator
    {
        if ($this->translator !== null) {
            return $this->translator;
        }

        // Neue Translator-Instanz erstellen, wenn keine vorhanden
        return new Translator(
            config('app.locale', 'de'),
            config('app.fallback_locale', 'en')
        );
    }

    /**
     * Extrahiert Regelnamen und Parameter aus einer Regel
     *
     * @param string|array $rule Die zu parsende Regel
     * @return array [ruleName, parameters]
     */
    private function parseRule(string|array $rule): array
    {
        $parameters = [];
        $ruleName = $rule;

        if (is_string($rule)) {
            if (str_contains($rule, ':')) {
                [$ruleName, $paramStr] = explode(':', $rule, 2);
                $parameters = explode(',', $paramStr);
            }
        } else if (is_array($rule)) {
            $parameters = array_slice($rule, 1);
            $ruleName = $rule[0];
        }

        return [$ruleName, $parameters];
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
        // Verwende die neue PHP 8.4 array_any Funktion
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
        if ($this->db === null || empty($parameters)) {
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
        if ($this->db === null || empty($parameters)) {
            return false;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $field;

        return $this->db->table($table)->where($column, '=', $value)->count() > 0;
    }

    /**
     * Validiert, ob ein Wert ein gültiger Enum-Wert ist
     *
     * @param string $field Feldname
     * @param mixed $value Wert
     * @param array $parameters Parameter (erste Parameter sollte ein Enum-Klassenname sein)
     * @param array $data Alle Daten
     * @return bool
     */
    protected function validateEnum(string $field, mixed $value, array $parameters, array $data): bool
    {
        if (empty($parameters)) {
            return false;
        }

        $enumClass = $parameters[0];

        // Prüfen, ob die Klasse existiert und ein Enum ist
        if (!class_exists($enumClass)) {
            return false;
        }

        // Reflektieren der Klasse, um zu prüfen, ob es ein Enum ist
        $reflector = new \ReflectionClass($enumClass);
        if (!$reflector->isEnum()) {
            return false;
        }

        // Für backed Enums
        if ($reflector->hasMethod('tryFrom')) {
            return $enumClass::tryFrom($value) !== null;
        }

        // Für reguläre Enums
        try {
            $cases = $enumClass::cases();
            foreach ($cases as $case) {
                if ($case->name === $value) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
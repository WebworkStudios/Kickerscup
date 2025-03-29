<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\ValidationRuleInterface;
use Throwable;

#[Injectable]
#[Singleton]
class Validator implements ValidatorInterface
{
    /**
     * Fehler nach der Validierung
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Registrierte Validierungsregeln
     *
     * @var array<string, ValidationRuleInterface|callable>
     */
    protected array $rules = [];

    /**
     * Benutzerdefinierte Fehlermeldungen
     *
     * @var array<string, string>
     */
    protected array $customMessages = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected ContainerInterface     $container,
        protected ?QueryBuilderInterface $database = null
    )
    {
        // Standard-Regeln registrieren
        $this->registerDefaultRules();
    }

    /**
     * Registriert die Standard-Validierungsregeln
     */
    protected function registerDefaultRules(): void
    {
        try {
            // Versuche Regeln über Container zu laden
            $this->rules['required'] = $this->container->get('App\\Infrastructure\\Validation\\Rules\\RequiredRule');
            $this->rules['email'] = $this->container->get('App\\Infrastructure\\Validation\\Rules\\EmailRule');
            $this->rules['numeric'] = $this->container->get('App\\Infrastructure\\Validation\\Rules\\NumericRule');

            // Direkte Callables für einfache Regeln
            $this->rules['min'] = fn($value, $params) => is_string($value) ?
                mb_strlen($value) >= ($params[0] ?? 0) :
                (is_numeric($value) ? $value >= ($params[0] ?? 0) : false);

            $this->rules['max'] = fn($value, $params) => is_string($value) ?
                mb_strlen($value) <= ($params[0] ?? 0) :
                (is_numeric($value) ? $value <= ($params[0] ?? 0) : false);

            // Standard-Fehlermeldungen
            $this->customMessages['min'] = 'Das Feld :field muss mindestens :param0 Zeichen haben.';
            $this->customMessages['max'] = 'Das Feld :field darf maximal :param0 Zeichen haben.';
        } catch (Throwable $e) {
            // Fehler bei Regel-Registrierung protokollieren
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        // Früher Ausstieg bei leeren Regeln
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $field => $fieldRules) {
            // Ermittle den Feldwert mit direktem Array-Zugriff
            $value = $data[$field] ?? null;

            // Konvertiere String-Regeln in ein Array
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            // Prüfe zuerst 'required'-Regel
            $isRequired = $this->isFieldRequired($fieldRules);

            // Wenn nicht required und Wert leer, überspringe weitere Validierungen
            if (!$isRequired && $this->isEmpty($value)) {
                continue;
            }

            // Validiere alle Regeln in optimierter Reihenfolge
            foreach ($fieldRules as $rule) {
                $ruleName = $this->parseRuleName($rule);
                $params = $this->parseRuleParams($rule);

                // Schneller Ausstieg bei required Regel
                if ($ruleName === 'required') {
                    if (!$this->validateSingle($value, $ruleName, $params, $field)) {
                        $errorMessage = $this->getErrorMessage($field, $ruleName, $params, $value);
                        $this->addError($field, $errorMessage);
                        break; // Überspringe andere Regeln, wenn required fehlschlägt
                    }
                    continue;
                }

                // Für alle anderen Regeln
                if (!$this->validateSingle($value, $ruleName, $params, $field)) {
                    $errorMessage = $this->getErrorMessage($field, $ruleName, $params, $value);
                    $this->addError($field, $errorMessage);
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Prüft, ob ein Feld als 'required' markiert ist
     */
    private function isFieldRequired(array $rules): bool
    {
        return array_any($rules, function ($rule) {
            if (is_string($rule)) {
                return $rule === 'required' || str_starts_with($rule, 'required:');
            } else if (is_array($rule)) {
                return ($rule['rule'] ?? '') === 'required';
            }
            return false;
        });
    }

    /**
     * Hilfsmethode für leere Wertprüfung
     */
    private function isEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Parst den Regelnamen aus einer Regel
     */
    private function parseRuleName($rule): string
    {
        if (is_string($rule)) {
            return str_contains($rule, ':') ?
                substr($rule, 0, strpos($rule, ':')) : $rule;
        } else if (is_array($rule)) {
            return $rule['rule'] ?? '';
        }
        return '';
    }

    /**
     * Parst die Parameter aus einer Regel
     */
    private function parseRuleParams($rule): array
    {
        if (is_string($rule) && str_contains($rule, ':')) {
            $paramStr = substr($rule, strpos($rule, ':') + 1);
            return explode(',', $paramStr);
        } else if (is_array($rule)) {
            return $rule['params'] ?? [];
        }
        return [];
    }

    /**
     * Validiert einen Wert gegen die Regel
     *
     * @param mixed $value Der zu validierende Wert
     * @param string $rule Die anzuwendende Regel
     * @param array $params Parameter für die Regel
     * @param string $field Name des Feldes (für Fehlermeldungen)
     * @return bool True, wenn die Validierung erfolgreich ist
     */
    public function validateSingle(mixed $value, string $rule, array $params = [], string $field = ''): bool
    {
        // Finde die passende Regel
        $ruleHandler = $this->rules[$rule] ?? null;

        if ($ruleHandler === null) {
            throw new ValidationException("Unbekannte Validierungsregel: $rule");
        }

        // Wenn es sich um eine ValidationRuleInterface-Instanz handelt
        if ($ruleHandler instanceof ValidationRuleInterface) {
            return $ruleHandler->validate($value, $params, $field);
        }

        // Wenn es sich um einen Callable handelt
        if (is_callable($ruleHandler)) {
            return $ruleHandler($value, $params, $field);
        }

        // Prüfe, ob eine interne Validierungsmethode existiert
        $methodName = 'validate' . ucfirst($rule);
        if (method_exists($this, $methodName)) {
            if ($rule === 'exists' || $rule === 'unique') {
                // Bei Datenbankregeln prüfen, ob die Datenbank verfügbar ist
                if ($this->database === null) {
                    throw new ValidationException("Datenbankverbindung für '$rule'-Validierung nicht verfügbar");
                }
            }

            return $this->$methodName($value, $params, $field);
        }

        throw new ValidationException("Keine gültige Implementierung für Regel: $rule");
    }

    /**
     * Erzeugt eine Fehlermeldung für eine fehlgeschlagene Validierung
     */
    protected function getErrorMessage(string $field, string $rule, array $params, mixed $value): string
    {
        // Zuerst prüfen, ob es eine benutzerdefinierte Meldung gibt
        if (isset($this->customMessages[$rule])) {
            $message = $this->customMessages[$rule];
        } else if (isset($this->rules[$rule]) && $this->rules[$rule] instanceof ValidationRuleInterface) {
            $message = $this->rules[$rule]->getMessage();
        } else {
            // Fallback-Meldung
            $message = "Die Validierung für das Feld $field mit der Regel '$rule' ist fehlgeschlagen.";
        }

        return $this->formatErrorMessage($message, $field, $rule, $params, $value);
    }

    /**
     * Formatiert eine Fehlermeldung mit Platzhaltern
     */
    protected function formatErrorMessage(string $message, string $field, string $rule, array $params, mixed $value = null): string
    {
        // Platzhalter ersetzen
        $replacements = [
            ':field' => $field,
            ':rule' => $rule,
            ':value' => is_scalar($value) ? (string)$value : gettype($value)
        ];

        // Parameter als Platzhalter hinzufügen
        foreach ($params as $index => $param) {
            $replacements[':param' . $index] = is_scalar($param) ? (string)$param : gettype($param);
        }

        return strtr($message, $replacements);
    }

    /**
     * Fügt einen Fehler zur Fehlerliste hinzu
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function addRule(string $name, callable $callback, ?string $errorMessage = null): static
    {
        $this->rules[$name] = $callback;

        if ($errorMessage !== null) {
            $this->customMessages[$name] = $errorMessage;
        }

        return $this;
    }

    /**
     * Überprüft, ob der Wert dem angegebenen Format entspricht
     */
    protected function validateFormat(mixed $value, array $params): bool
    {
        if (empty($params[0]) || !is_string($value)) {
            return false;
        }

        return preg_match($params[0], $value) === 1;
    }

    /**
     * Überprüft, ob ein Wert in der Datenbank existiert
     *
     * @throws ValidationException Wenn die Datenbankverbindung nicht verfügbar ist oder Parameter fehlen
     */
    protected function validateExists(mixed $value, array $params): bool
    {
        if ($this->database === null) {
            throw new ValidationException("Datenbankverbindung für 'exists'-Validierung nicht verfügbar");
        }

        if (count($params) < 2) {
            throw new ValidationException("Die 'exists'-Regel benötigt mindestens zwei Parameter: Tabelle und Spalte");
        }

        $table = $params[0];
        $column = $params[1];

        try {
            $result = $this->database->table($table)
                ->select('COUNT(*) as count')
                ->where($column, '=', $value)
                ->first();

            return ($result['count'] ?? 0) > 0;
        } catch (Throwable $e) {
            throw new ValidationException("Datenbankfehler bei 'exists'-Validierung: " . $e->getMessage());
        }
    }

    /**
     * Überprüft, ob ein Wert in der Datenbank einzigartig ist
     *
     * @throws ValidationException Wenn die Datenbankverbindung nicht verfügbar ist oder Parameter fehlen
     */
    protected function validateUnique(mixed $value, array $params): bool
    {
        if ($this->database === null) {
            throw new ValidationException("Datenbankverbindung für 'unique'-Validierung nicht verfügbar");
        }

        if (count($params) < 2) {
            throw new ValidationException("Die 'unique'-Regel benötigt mindestens zwei Parameter: Tabelle und Spalte");
        }

        $table = $params[0];
        $column = $params[1];

        // Optionaler Ausschluss für Updates
        $ignoreId = $params[2] ?? null;
        $idColumn = $params[3] ?? 'id';

        try {
            $query = $this->database->table($table)
                ->select('COUNT(*) as count')
                ->where($column, '=', $value);

            if ($ignoreId !== null) {
                $query->where($idColumn, '!=', $ignoreId);
            }

            $result = $query->first();

            return ($result['count'] ?? 0) === 0;
        } catch (Throwable $e) {
            throw new ValidationException("Datenbankfehler bei 'unique'-Validierung: " . $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Validation\ValidationException;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\ValidationRuleRegistry;

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
     * Benutzerdefinierte Validierungsregeln
     *
     * @var array<string, array{callback: callable, message: string}>
     */
    protected array $customRules = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected ValidationRuleRegistry $ruleRegistry,
        protected ContainerInterface     $container,
        protected ?QueryBuilderInterface $database = null
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Konvertiere String-Regeln in ein Array
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                $params = [];

                // Behandle Regeln mit Parametern (z.B. max: 255)
                if (is_string($rule) && str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                    $rule = $ruleName;
                } elseif (is_array($rule)) {
                    // Array-Format: ['rule' => 'max', 'params' => [255]]
                    $params = $rule['params'] ?? [];
                    $rule = $rule['rule'];
                }

                if (!$this->validateSingle($value, $rule, $params, $field)) {
                    if (!isset($this->errors[$field])) {
                        $this->errors[$field] = [];
                    }
                    // Füge die Fehlermeldung hinzu
                    $this->errors[$field][] = $this->getErrorMessage($field, $rule, $params, $value);

                    // Bei 'required' Validierungen, breche weitere Validierungen für dieses Feld ab,
                    // wenn es leer ist und die required-Validierung fehlschlägt
                    if ($rule === 'required' && empty($value)) {
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function validateSingle(mixed $value, string $rule, array $params = [], string $field = ''): bool
    {
        // Prüfe zuerst benutzerdefinierte Regeln
        if (isset($this->customRules[$rule])) {
            return (bool)($this->customRules[$rule]['callback'])($value, $params, $field);
        }

        // Prüfe auf Methode in dieser Klasse
        $methodName = 'validate' . ucfirst($rule);
        if (method_exists($this, $methodName)) {
            return $this->$methodName($value, $params, $field);
        }

        // Versuche, die Regel aus dem Registry zu holen
        $ruleInstance = $this->ruleRegistry->getRule($rule);
        if ($ruleInstance !== null) {
            return $ruleInstance->validate($value, $params, $field);
        }

        // Wenn keine Regel gefunden wurde
        throw new ValidationException("Unbekannte Validierungsregel: $rule");
    }

    /**
     * Generiert eine Fehlermeldung für eine fehlgeschlagene Validierung
     *
     * @param string $field Das Feld, das validiert wurde
     * @param string $rule Die angewendete Regel
     * @param array<string, mixed> $params Die Parameter der Regel
     * @param mixed $value Der validierte Wert
     * @return string Die Fehlermeldung
     */
    protected function getErrorMessage(string $field, string $rule, array $params, mixed $value): string
    {
        // Zuerst prüfen, ob es eine benutzerdefinierte Meldung gibt
        if (isset($this->customRules[$rule])) {
            $message = $this->customRules[$rule]['message'];
        } else {
            // Standardmeldungen aus dem Registry holen
            $message = $this->ruleRegistry->getErrorMessage($rule);
            
            // Fallback, falls keine Nachricht gefunden wurde
            if (empty($message)) {
                $message = "Die Validierung für das Feld :field mit der Regel '{$rule}' ist fehlgeschlagen.";
            }
        }

        // Platzhalter ersetzen
        $replacements = [
            ':field' => $field,
            ':value' => is_scalar($value) ? (string)$value : gettype($value)
        ];

        // Parameter als Platzhalter hinzufügen (: param0, : param1, etc.)
        foreach ($params as $index => $param) {
            $replacements[':param' . $index] = is_scalar($param) ? (string)$param : gettype($param);
        }

        return strtr($message, $replacements);
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
        $this->customRules[$name] = [
            'callback' => $callback,
            'message' => $errorMessage ?? "Die Validierung für das Feld :field mit der Regel '{$name}' ist fehlgeschlagen."
        ];

        return $this;
    }

    /**
     * Überprüft, ob der Wert dem angegebenen Format entspricht
     */
    protected function validateFormat(mixed $value, array $params, string $field): bool
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
    protected function validateExists(mixed $value, array $params, string $field): bool
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
        } catch (\Throwable $e) {
            throw new ValidationException("Datenbankfehler bei 'exists'-Validierung: " . $e->getMessage());
        }
    }

    /**
     * Überprüft, ob ein Wert in der Datenbank einzigartig ist
     * 
     * @throws ValidationException Wenn die Datenbankverbindung nicht verfügbar ist oder Parameter fehlen
     */
    protected function validateUnique(mixed $value, array $params, string $field): bool
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
        } catch (\Throwable $e) {
            throw new ValidationException("Datenbankfehler bei 'unique'-Validierung: " . $e->getMessage());
        }
    }
}

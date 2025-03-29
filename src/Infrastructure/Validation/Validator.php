<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\ValidationRuleInterface;
use App\Infrastructure\Validation\Rules\ValidationRuleRegistry;
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
     * Benutzerdefinierte Fehlermeldungen
     *
     * @var array<string, string>
     */
    protected array $customMessages = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected ValidationRuleRegistry $ruleRegistry
    )
    {
        // Standard-Fehlermeldungen für einfache Regeln
        $this->customMessages['min'] = 'Das Feld :field muss mindestens :param0 Zeichen haben.';
        $this->customMessages['max'] = 'Das Feld :field darf maximal :param0 Zeichen haben.';
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
        try {
            // Holen der Regel aus der Registry
            $ruleInstance = $this->ruleRegistry->getRule($rule);

            if ($ruleInstance !== null) {
                return $ruleInstance->validate($value, $params, $field);
            }

            // Spezielle Behandlung für min/max als Callable-Regeln
            if ($rule === 'min') {
                return is_string($value) ?
                    mb_strlen($value) >= ($params[0] ?? 0) :
                    (is_numeric($value) ? $value >= ($params[0] ?? 0) : false);
            }

            if ($rule === 'max') {
                return is_string($value) ?
                    mb_strlen($value) <= ($params[0] ?? 0) :
                    (is_numeric($value) ? $value <= ($params[0] ?? 0) : false);
            }

            // Falls keine Regel gefunden wurde
            throw new ValidationException("Unbekannte Validierungsregel: $rule");
        } catch (Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw new ValidationException("Fehler bei der Validierung mit Regel '$rule': " . $e->getMessage());
        }
    }

    /**
     * Erzeugt eine Fehlermeldung für eine fehlgeschlagene Validierung
     */
    protected function getErrorMessage(string $field, string $rule, array $params, mixed $value): string
    {
        // Zuerst prüfen, ob es eine benutzerdefinierte Meldung gibt
        if (isset($this->customMessages[$rule])) {
            $message = $this->customMessages[$rule];
        } else {
            // Holen der Meldung aus der Registry
            $message = $this->ruleRegistry->getErrorMessage($rule);
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
        // Bei Callable-Regeln verwenden wir einen speziellen Wrapper, um sie in der Registry zu registrieren
        $rule = new class($callback, $errorMessage ?? "Validierung mit Regel '$name' fehlgeschlagen.") implements ValidationRuleInterface {
            private $callback;
            private $message;

            public function __construct(callable $callback, string $message) {
                $this->callback = $callback;
                $this->message = $message;
            }

            public function validate(mixed $value, array $params, string $field): bool {
                return ($this->callback)($value, $params, $field);
            }

            public function getMessage(): string {
                return $this->message;
            }
        };

        $this->ruleRegistry->registerRule($name, $rule);

        if ($errorMessage !== null) {
            $this->customMessages[$name] = $errorMessage;
        }

        return $this;
    }
}
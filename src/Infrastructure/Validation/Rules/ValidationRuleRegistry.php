<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use InvalidArgumentException;
use Throwable;

#[Injectable]
#[Singleton]
class ValidationRuleRegistry
{
    /**
     * Verfügbare Validierungsregeln
     *
     * @var array<string, ValidationRuleInterface>
     */
    protected array $rules = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected ContainerInterface $container
    )
    {
        $this->registerDefaultRules();
    }

    /**
     * Registriert Standardregeln
     */
    protected function registerDefaultRules(): void
    {
        $this->registerRule('required', RequiredRule::class)
            ->registerRule('email', EmailRule::class)
            ->registerRule('numeric', NumericRule::class);
        // ... weitere Regeln
    }

    /**
     * Registriert eine Validierungsregel
     *
     * @param string $name Name der Regel
     * @param ValidationRuleInterface|string $rule Regel-Instanz oder Klassenname
     * @return self
     * @throws InvalidArgumentException wenn die Regel ungültig ist
     */
    public function registerRule(string $name, ValidationRuleInterface|string $rule): self
    {
        if (is_string($rule) && class_exists($rule)) {
            try {
                $rule = $this->container->get($rule);
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    "Konnte Regel '$rule' nicht instanziieren: " . $e->getMessage()
                );
            }
        }

        if (!$rule instanceof ValidationRuleInterface) {
            throw new InvalidArgumentException(
                "Die Regel muss eine Instanz von ValidationRuleInterface sein."
            );
        }

        $this->rules[$name] = $rule;
        return $this;
    }

    /**
     * Prüft, ob eine Regel existiert
     *
     * @param string $name Name der Regel
     * @return bool
     */
    public function hasRule(string $name): bool
    {
        return array_key_exists($name, $this->rules);
    }

    /**
     * Gibt alle registrierten Regeln zurück
     *
     * @return array<string, ValidationRuleInterface>
     */
    public function getAllRules(): array
    {
        return $this->rules;
    }

    /**
     * Gibt die Fehlermeldung für eine Regel zurück
     *
     * @param string $name Name der Regel
     * @return string Die Fehlermeldung
     */
    public function getErrorMessage(string $name): string
    {
        $rule = $this->getRule($name);

        if ($rule !== null) {
            return $rule->getMessage();
        }

        return "Das Feld :field hat die Validierung '$name' nicht bestanden.";
    }

    /**
     * Gibt eine Instanz einer Regel zurück
     *
     * @param string $name Name der Regel
     * @return ValidationRuleInterface|null Die Regelinstanz oder null
     */
    public function getRule(string $name): ?ValidationRuleInterface
    {
        return $this->rules[$name] ?? null;
    }
}

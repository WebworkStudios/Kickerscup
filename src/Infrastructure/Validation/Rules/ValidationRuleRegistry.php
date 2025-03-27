<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;

#[Injectable]
#[Singleton]
class ValidationRuleRegistry
{
    /**
     * Verfügbare Validierungsregeln
     *
     * @var array<string, class-string<ValidationRuleInterface>>
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
        $this->registerRule('required', RequiredRule::class);
        $this->registerRule('email', EmailRule::class);
        $this->registerRule('numeric', NumericRule::class);
        // ... weitere Regeln
    }

    /**
     * Registriert eine Validierungsregel
     *
     * @param string $name Name der Regel
     * @param class-string<ValidationRuleInterface> $ruleClass Klassenname der Regel
     * @return void
     */
    public function registerRule(string $name, string $ruleClass): void
    {
        $this->rules[$name] = $ruleClass;
    }

    /**
     * Gibt eine Instanz einer Regel zurück
     *
     * @param string $name Name der Regel
     * @return ValidationRuleInterface|null Die Regelinstanz oder null
     */
    public function getRule(string $name): ?ValidationRuleInterface
    {
        if (!isset($this->rules[$name])) {
            return null;
        }

        $ruleClass = $this->rules[$name];

        try {
            return $this->container->get($ruleClass);
        } catch (\Throwable) {
            return null;
        }
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
}
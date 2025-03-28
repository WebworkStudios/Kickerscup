<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\ValidationRuleRegistry;
use App\Infrastructure\Validation\Rules\EmailRule;
use App\Infrastructure\Validation\Rules\RequiredRule;
use App\Infrastructure\Validation\Rules\NumericRule;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Registriert Validierungs-Services im Container
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere das Rule Registry als Singleton
        $container->singleton(ValidationRuleRegistry::class);

        // Registriere die Validierungsregeln
        $container->singleton(RequiredRule::class);
        $container->singleton(EmailRule::class);
        $container->singleton(NumericRule::class);

        // Hier kommt die wichtige Änderung:
        // Statt direkt eine Validator-Instanz zu erstellen,
        // registrieren wir die Klasse mit einer Closure
        $container->bind(ValidatorInterface::class, function(ContainerInterface $c) {
            $ruleRegistry = $c->get(ValidationRuleRegistry::class);
            return new Validator($ruleRegistry, $c, null);
        });

        // Gleiche Bindung für die konkrete Klasse
        $container->bind(Validator::class, function(ContainerInterface $c) {
            return $c->get(ValidatorInterface::class);
        });

        // Registriere den RequestValidator als Singleton
        $container->singleton(RequestValidator::class);

        // Initialisiere das Rule Registry mit Standardregeln
        $this->initializeRuleRegistry($container);
    }

    /**
     * Initialisiert das Rule Registry mit Standardregeln
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function initializeRuleRegistry(ContainerInterface $container): void
    {
        $registry = $container->get(ValidationRuleRegistry::class);

        // Registriere die Standardregeln im Registry
        $registry->registerRule('required', $container->get(RequiredRule::class));
        $registry->registerRule('email', $container->get(EmailRule::class));
        $registry->registerRule('numeric', $container->get(NumericRule::class));
    }
}
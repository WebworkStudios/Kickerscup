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

        // Erstelle eine Validator-Instanz mit null für die Datenbank
        $ruleRegistry = $container->get(ValidationRuleRegistry::class);
        $validator = new Validator($ruleRegistry, $container, null);

        // Binde den Validator direkt als Instanz
        $container->bind(ValidatorInterface::class, $validator);
        $container->bind(Validator::class, $validator);

        // Registriere den RequestValidator
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
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

        // Registriere den Validator als Singleton
        $container->singleton(ValidatorInterface::class, Validator::class);
        $container->singleton(Validator::class);

        // Registriere den RequestValidator als Singleton
        $container->singleton(RequestValidator::class);
    }
}
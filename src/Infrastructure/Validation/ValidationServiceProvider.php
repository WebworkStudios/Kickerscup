<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\DateRule;
use App\Infrastructure\Validation\Rules\EmailRule;
use App\Infrastructure\Validation\Rules\NumericRule;
use App\Infrastructure\Validation\Rules\RequiredRule;
use App\Infrastructure\Validation\Rules\StringLengthRule;
use App\Infrastructure\Validation\Rules\ValidationRuleRegistry;
use Throwable;

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
        $container->singleton(DateRule::class);
        $container->singleton(StringLengthRule::class);

        // Registriere den Validator als Singleton mit Closure für bedingte Datenbankabhängigkeit
        $container->singleton(ValidatorInterface::class, function (ContainerInterface $c) {
            $database = null;
            if ($c->has('App\\Infrastructure\\Database\\Contracts\\QueryBuilderInterface')) {
                try {
                    $database = $c->get('App\\Infrastructure\\Database\\Contracts\\QueryBuilderInterface');
                } catch (Throwable) {
                    // Ignoriere den Fehler, Validator wird mit null-Datenbank erstellt
                }
            }

            return new Validator($c, $database);
        });
        $container->singleton(Validator::class);

        // Registriere den RequestValidator als Singleton
        $container->singleton(RequestValidator::class);
    }
}
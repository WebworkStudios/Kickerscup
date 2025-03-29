<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\Rules\AlphaNumericRule;
use App\Infrastructure\Validation\Rules\AlphaRule;
use App\Infrastructure\Validation\Rules\BooleanRule;
use App\Infrastructure\Validation\Rules\ComparisonRule;
use App\Infrastructure\Validation\Rules\ConfirmationRule;
use App\Infrastructure\Validation\Rules\DateRule;
use App\Infrastructure\Validation\Rules\EmailRule;
use App\Infrastructure\Validation\Rules\ExistsRule;
use App\Infrastructure\Validation\Rules\FileTypeRule;
use App\Infrastructure\Validation\Rules\FileSizeRule;
use App\Infrastructure\Validation\Rules\ImageDimensionsRule;
use App\Infrastructure\Validation\Rules\InRule;
use App\Infrastructure\Validation\Rules\JsonRule;
use App\Infrastructure\Validation\Rules\NotInRule;
use App\Infrastructure\Validation\Rules\NumericRule;
use App\Infrastructure\Validation\Rules\PhoneNumberRule;
use App\Infrastructure\Validation\Rules\RequiredRule;
use App\Infrastructure\Validation\Rules\StringLengthRule;
use App\Infrastructure\Validation\Rules\UniqueRule;
use App\Infrastructure\Validation\Rules\ValidationRuleRegistry;

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

        // Registriere die neuen datenbankbezogenen Regeln
        $container->singleton(ExistsRule::class);
        $container->singleton(UniqueRule::class);

        // Registriere die weiteren Validierungsregeln
        $container->singleton(AlphaNumericRule::class);
        $container->singleton(AlphaRule::class);
        $container->singleton(BooleanRule::class);
        $container->singleton(ComparisonRule::class);
        $container->singleton(ConfirmationRule::class);
        $container->singleton(JsonRule::class);
        $container->singleton(InRule::class);
        $container->singleton(NotInRule::class);
        $container->singleton(FileTypeRule::class);
        $container->singleton(FileSizeRule::class);
        $container->singleton(ImageDimensionsRule::class);
        $container->singleton(PhoneNumberRule::class);

        // Registriere den Validator als Singleton
        // Wichtig: Der Validator benötigt jetzt die ValidationRuleRegistry
        $container->singleton(ValidatorInterface::class, Validator::class);
        $container->singleton(Validator::class);

        // Registriere den RequestValidator als Singleton
        $container->singleton(RequestValidator::class);
    }
}
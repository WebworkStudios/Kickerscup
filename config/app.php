<?php

declare(strict_types=1);

/**
 * Application Configuration
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application.
    |
    */
    'name' => 'PHP Application',

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes.
    |
    */
    'env' => 'development',

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */
    'debug' => true,

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the framework when generating URLs.
    |
    */
    'url' => 'http://localhost',

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application.
    |
    */
    'providers' => [
        // Add your application-specific service providers here
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Directories
    |--------------------------------------------------------------------------
    |
    | List of directories that should be scanned for autowiring.
    |
    */
    'scan_directories' => [
        APP_ROOT . '/src/Application',
        APP_ROOT . '/src/Domain',
        APP_ROOT . '/src/Infrastructure',
        APP_ROOT . '/src/Presentation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Directories
    |--------------------------------------------------------------------------
    |
    | List of directories that should be scanned for routes.
    |
    */
    'route_directories' => [
        APP_ROOT . '/src/Presentation/Controllers',
        APP_ROOT . '/src/Presentation/Actions',
    ],
];
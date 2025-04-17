<?php


declare(strict_types=1);

/**
 * Anwendungs-Konfiguration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Anwendungsdebug
    |--------------------------------------------------------------------------
    |
    | Wenn dieser Wert auf true gesetzt ist, werden detaillierte Fehlermeldungen angezeigt.
    |
    */
    'debug' => env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Anwendungs-URL
    |--------------------------------------------------------------------------
    |
    | Die URL, unter der die Anwendung erreichbar ist.
    |
    */
    'url' => env('APP_URL', 'http://kickerscup.local'),

    /*
    |--------------------------------------------------------------------------
    | Anwendungssprache
    |--------------------------------------------------------------------------
    |
    | Die Standardsprache der Anwendung.
    |
    */
    'locale' => env('APP_LOCALE', 'de'),

    /*
    |--------------------------------------------------------------------------
    | Fallback-Sprache
    |--------------------------------------------------------------------------
    |
    | Die Fallback-Sprache, wenn die angeforderte Sprache nicht verfügbar ist.
    |
    */
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
];
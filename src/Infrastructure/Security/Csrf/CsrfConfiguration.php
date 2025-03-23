<?php


declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
readonly class CsrfConfiguration
{
    public function __construct(
        /**
         * Standardlebensdauer für Tokens in Sekunden (0 = unbegrenzt)
         */
        public int    $defaultTokenLifetime = 7200,

        /**
         * Vertrauenswürdige Ursprünge
         * @var array<string>
         */
        public array  $trustedOrigins = [],

        /**
         * Ob ein Origin/Referer-Header erforderlich ist
         */
        public bool   $requireOriginHeader = true,

        /**
         * Pfade, die vom CSRF-Schutz ausgeschlossen sind
         * @var array<string>
         */
        public array  $excludedPaths = ['/api/', '/webhook/'],

        /**
         * Name des CSRF-Tokens in Formularen
         */
        public string $tokenParameterName = '_csrf_token',

        /**
         * Name der Cookie für Double-Submit-Cookies
         */
        public string $cookieName = 'csrf_token',

        /**
         * Ob Double-Submit-Cookies verwendet werden sollen
         */
        public bool   $useDoubleSubmitCookie = false
    )
    {
    }
}
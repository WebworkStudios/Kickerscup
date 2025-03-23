<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing\Attributes;

use Attribute;

/**
 * CORS-Konfiguration für eine Route oder Routengruppe
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class Cors
{
    /**
     * Konstruktor
     *
     * @param array|string $allowOrigin Erlaubte Herkunft (z.B. '*', 'example.com' oder ['a.com', 'b.com'])
     * @param array|string $allowMethods Erlaubte HTTP-Methoden (z.B. '*' oder ['GET', 'POST'])
     * @param array|string $allowHeaders Erlaubte HTTP-Header (z.B. '*' oder ['X-Custom-Header'])
     * @param bool $allowCredentials Ob Cookies in Cross-Origin-Anfragen erlaubt sind
     * @param int $maxAge Wie lange die Preflight-Ergebnisse (in Sekunden) gecacht werden sollen
     * @param array|string $exposeHeaders Header, die im Browser zugänglich gemacht werden sollen
     */
    public function __construct(
        public array|string $allowOrigin = '*',
        public array|string $allowMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        public array|string $allowHeaders = '*',
        public bool         $allowCredentials = false,
        public int          $maxAge = 86400, // 24 Stunden
        public array|string $exposeHeaders = []
    )
    {
    }
}
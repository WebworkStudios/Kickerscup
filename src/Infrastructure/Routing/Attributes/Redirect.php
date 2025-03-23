<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Attributes;

use Attribute;

/**
 * Redirect Attribut
 * definiert eine Umleitung von einem Pfad zu einem anderen
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class Redirect
{
    /**
     * Konstruktor
     *
     * @param string $fromPath Der Quellpfad (von dem umgeleitet wird)
     * @param string $toPath Der Zielpfad (zu dem umgeleitet wird) oder benannte Route mit 'name: routeName'
     * @param int $statusCode HTTP-Statuscode für die Umleitung (301 = permanent, 302 = temporär)
     * @param bool $preserveQueryString Ob der Query-String übernommen werden soll
     */
    public function __construct(
        public string $fromPath,
        public string $toPath,
        public int    $statusCode = 302,
        public bool   $preserveQueryString = true
    )
    {
    }
}
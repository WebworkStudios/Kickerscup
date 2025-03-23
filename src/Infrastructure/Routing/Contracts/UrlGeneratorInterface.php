<?php
declare(strict_types=1);

namespace App\Infrastructure\Routing\Contracts;


/**
 * Interface für URL-Generator
 */
interface UrlGeneratorInterface
{
    /**
     * Generiert eine URL für eine benannte Route
     *
     * @param string $name Name der Route
     * @param array $parameters Parameter für die URL
     * @param bool $absoluteUrl Ob eine absolute URL generiert werden soll
     * @return string Die generierte URL
     */
    public function generate(string $name, array $parameters = [], bool $absoluteUrl = false): string;
}
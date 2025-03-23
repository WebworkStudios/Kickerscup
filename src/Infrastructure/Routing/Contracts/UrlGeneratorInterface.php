<?php
declare(strict_types=1);

namespace App\Infrastructure\Routing\Contracts;


/**
 * Interface für URL-Generator
 */
interface UrlGeneratorInterface
{
    /**
     * Generiert eine URL für einen benannten Route
     *
     * @param string $name Name der Route
     * @param array $parameters Parameter für die URL
     * @return string Die generierte URL
     */
    public function generate(string $name, array $parameters = []): string;
}
<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Contracts;

/**
 * Interface für den Route-Scanner
 */
interface RouteScannerInterface
{
    /**
     * Scannt Verzeichnisse nach Routen-Attributen
     *
     * @param array $directories Zu scannende Verzeichnisse
     * @param string $namespace Basis-Namespace
     * @return void
     */
    public function scan(array $directories, string $namespace = ''): void;
}
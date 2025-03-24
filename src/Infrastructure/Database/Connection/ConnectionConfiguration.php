<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Connection;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class ConnectionConfiguration
{
    /**
     * @param string $driver Datenbanktyp (mysql, pgsql, sqlite)
     * @param string $host Datenbank-Host
     * @param string $database Datenbankname
     * @param string $username Benutzername
     * @param string $password Passwort
     * @param int|null $port Port (optional)
     * @param string|null $charset Zeichensatz (optional)
     * @param array $options Zusätzliche PDO-Optionen
     */
    public function __construct(
        public readonly string  $driver = 'mysql',
        public readonly string  $host = 'localhost',
        public readonly string  $database = '',
        public readonly string  $username = '',
        public readonly string  $password = '',
        public readonly ?int    $port = null,
        public readonly ?string $charset = 'utf8mb4',
        public readonly array   $options = []
    )
    {
    }
}
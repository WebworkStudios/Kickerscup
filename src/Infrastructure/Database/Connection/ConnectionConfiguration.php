<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Connection;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
readonly class ConnectionConfiguration
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
        public string  $driver = 'mysql',
        public string  $host = 'localhost',
        public string  $database = '',
        public string  $username = '',
        public string  $password = '',
        public ?int    $port = null,
        public ?string $charset = 'utf8mb4',
        public array   $options = []
    )
    {
    }
}
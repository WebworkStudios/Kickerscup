<?php


declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Config\Config;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Database\Connection\ConnectionConfiguration;
use App\Infrastructure\Database\Connection\ConnectionManager;
use RuntimeException;

#[Injectable]
class DatabaseConfiguration
{
    public function __construct(
        private readonly Config $config
    )
    {
    }

    public function configure(ConnectionManager $connectionManager): void
    {
        // Holen der Datenbankkonfiguration aus config/database.php
        $defaultConnection = $this->config->get('database.default', 'mysql');
        $connections = $this->config->get('database.connections', []);

        // Überprüfe, ob die Standardverbindung konfiguriert ist
        if (!isset($connections[$defaultConnection])) {
            throw new RuntimeException("Die Standardverbindung '$defaultConnection' ist nicht konfiguriert");
        }

        $dbConfig = $connections[$defaultConnection];

        // Konfiguriere die Standardverbindung
        $defaultConfig = new ConnectionConfiguration(
            host: $dbConfig['host'] ?? 'localhost',
            database: $dbConfig['database'] ?? 'testdb',
            username: $dbConfig['username'] ?? 'root',
            password: $dbConfig['password'] ?? '',
            port: $dbConfig['port'] ?? 3306,
            charset: $dbConfig['charset'] ?? 'utf8mb4',
            options: $dbConfig['options'] ?? []
        );

        $connectionManager->addConnection('default', $defaultConfig);
        $connectionManager->setDefaultConnection('default');

        // Konfiguriere zusätzliche Verbindungen, wenn vorhanden
        foreach ($connections as $name => $config) {
            if ($name !== $defaultConnection) {
                $connectionManager->addConnection($name, new ConnectionConfiguration(
                    host: $config['host'] ?? 'localhost',
                    database: $config['database'] ?? '',
                    username: $config['username'] ?? 'root',
                    password: $config['password'] ?? '',
                    port: $config['port'] ?? 3306,
                    charset: $config['charset'] ?? 'utf8mb4',
                    options: $config['options'] ?? []
                ));
            }
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Database\Connection\ConnectionConfiguration;
use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\Contracts\ResultHandlerInterface;
use App\Infrastructure\Database\QueryBuilder\DeleteQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\InsertQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\SelectQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\UpdateQueryBuilder;
use App\Infrastructure\Database\Result\ResultHandler;
use App\Infrastructure\Logging\Contracts\LoggerInterface;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Hole Logger für Debugging
        $logger = null;
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info('DatabaseServiceProvider: Registrierung gestartet');
        }

        // Registriere den Connection Manager
        $container->singleton(ConnectionManager::class);

        if ($logger) {
            $logger->info('DatabaseServiceProvider: ConnectionManager registriert');
        }

        // Konfiguriere die Datenbank-Verbindungen
        $this->configureConnections($container, $logger);

        // Registriere den Query Debugger
        $container->singleton(Debug\QueryDebugger::class);

        // Registriere den Statement Cache
        $container->singleton(Cache\StatementCache::class);

        // Registriere den Result Handler
        $container->bind(ResultHandlerInterface::class, ResultHandler::class);

        // Registriere die Query Builder
        $container->bind(SelectQueryBuilder::class);
        $container->bind(InsertQueryBuilder::class);
        $container->bind(UpdateQueryBuilder::class);
        $container->bind(DeleteQueryBuilder::class);

        if ($logger) {
            $logger->info('DatabaseServiceProvider: Registrierung abgeschlossen');
        }
    }

    /**
     * Konfiguriert die Datenbankverbindungen aus der Konfiguration
     */
    protected function configureConnections(ContainerInterface $container, ?LoggerInterface $logger = null): void
    {
        if (!$container->has('config')) {
            if ($logger) {
                $logger->warning('DatabaseServiceProvider: Config nicht im Container gefunden');
            }
            return;
        }

        $config = $container->get('config');

        if (!method_exists($config, 'get')) {
            if ($logger) {
                $logger->warning('DatabaseServiceProvider: Config-Objekt hat keine get-Methode');
            }
            return;
        }

        $connectionManager = $container->get(ConnectionManager::class);

        // Default-Verbindung setzen
        $defaultConnection = $config->get('database.default', 'mysql');

        if ($logger) {
            $logger->info('DatabaseServiceProvider: Default-Verbindung', ['connection' => $defaultConnection]);
        }

        // Verbindungen aus der Konfiguration laden
        $connections = $config->get('database.connections', []);

        if ($logger) {
            $logger->info('DatabaseServiceProvider: Geladene Verbindungen', ['count' => count($connections), 'keys' => array_keys($connections)]);
        }

        foreach ($connections as $name => $connectionConfig) {
            // Überspringe ungültige Konfigurationen
            if (!isset($connectionConfig['host']) || !isset($connectionConfig['database'])) {
                if ($logger) {
                    $logger->warning('DatabaseServiceProvider: Ungültige Konfiguration für', ['connection' => $name]);
                }
                continue;
            }

            if ($logger) {
                $logger->info('DatabaseServiceProvider: Konfiguriere Verbindung', ['connection' => $name]);
            }

            // Erstelle die Verbindungskonfiguration
            $connConfig = new ConnectionConfiguration(
                host: $connectionConfig['host'],
                database: $connectionConfig['database'],
                username: $connectionConfig['username'] ?? '',
                password: $connectionConfig['password'] ?? '',
                port: $connectionConfig['port'] ?? null,
                charset: $connectionConfig['charset'] ?? 'utf8mb4',
                options: $connectionConfig['options'] ?? []
            );

            // Füge die Verbindung zum Manager hinzu
            $connectionManager->addConnection($name, $connConfig);

            if ($logger) {
                $logger->info('DatabaseServiceProvider: Verbindung hinzugefügt', ['connection' => $name]);
            }
        }

        // Setze die Standardverbindung
        if (isset($connections[$defaultConnection])) {
            $connectionManager->setDefaultConnection($defaultConnection);

            if ($logger) {
                $logger->info('DatabaseServiceProvider: Standardverbindung gesetzt', ['connection' => $defaultConnection]);
            }
        } else {
            if ($logger) {
                $logger->warning('DatabaseServiceProvider: Standardverbindung nicht gefunden', ['connection' => $defaultConnection]);
            }
        }
    }
}
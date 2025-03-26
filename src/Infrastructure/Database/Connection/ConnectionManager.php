<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Connection;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Database\Contracts\ConnectionInterface;
use App\Infrastructure\Database\Exceptions\ConnectionException;
use App\Infrastructure\Logging\Contracts\LoggerInterface;

#[Injectable]
#[Singleton]
class ConnectionManager
{
    /**
     * Aktive Datenbankverbindungen
     *
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    /**
     * Konfigurationen für verschiedene Verbindungen
     *
     * @var array<string, ConnectionConfiguration>
     */
    private array $configurations = [];

    /**
     * Name der Standardverbindung
     */
    private string $defaultConnection = 'default';

    /**
     * Container für Dependency Injection
     */
    private ?ContainerInterface $container;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?ContainerInterface $container = null
    )
    {
        $this->container = $container;
    }

    /**
     * Konfiguriert eine Datenbankverbindung
     *
     * @param string $name Name der Verbindung
     * @param ConnectionConfiguration $config Konfiguration für die Verbindung
     * @return self
     */
    public function addConnection(string $name, ConnectionConfiguration $config): self
    {
        $this->configurations[$name] = $config;
        return $this;
    }

    /**
     * Legt den Namen der Standardverbindung fest
     *
     * @param string $name Name der Standardverbindung
     * @return self
     */
    public function setDefaultConnection(string $name): self
    {
        if (!isset($this->configurations[$name])) {
            throw new ConnectionException("Connection configuration '{$name}' does not exist");
        }

        $this->defaultConnection = $name;
        return $this;
    }

    /**
     * Gibt den Namen der Standardverbindung zurück
     *
     * @return string
     */
    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Findet eine Datenbankverbindung anhand des Host-Namens
     *
     * Diese Methode durchsucht alle konfigurierten Verbindungen und gibt
     * die erste Verbindung zurück, die dem angegebenen Host entspricht.
     * Nutzt PHP 8.4's array_find_key für effiziente Suche.
     *
     * @param string $host Der zu suchende Host-Name
     * @return ConnectionInterface|null Die gefundene Verbindung oder null, wenn keine gefunden wurde
     */
    public function findConnectionByHost(string $host): ?ConnectionInterface
    {
        // Finde den Konfigurationsnamen, der dem gesuchten Host entspricht
        $connectionName = array_find_key(
            $this->configurations,
            fn($config, $name) => $config->host === $host
        );

        // Wenn ein passender Name gefunden wurde, hole die zugehörige Verbindung
        if ($connectionName !== null) {
            return $this->getConnection($connectionName);
        }

        // Wenn nichts gefunden wurde, gib null zurück
        return null;
    }

    /**
     * Holt eine Datenbankverbindung
     *
     * @param string|null $name Name der Verbindung (oder null für die Standardverbindung)
     * @return ConnectionInterface
     * @throws ConnectionException Wenn die Verbindungskonfiguration nicht existiert
     */
    public function getConnection(?string $name = null): ConnectionInterface
    {
        $connectionName = $name ?? $this->defaultConnection;

        // Wenn die Verbindung bereits existiert, gib sie zurück
        if (isset($this->connections[$connectionName])) {
            return $this->connections[$connectionName];
        }

        // Prüfe, ob eine Konfiguration für die Verbindung existiert
        if (!isset($this->configurations[$connectionName])) {
            throw new ConnectionException("No configuration found for connection '{$connectionName}'");
        }

        // Erstelle eine neue Verbindung
        $connection = new Connection(
            $this->configurations[$connectionName],
            $this->logger,
            $this->container
        );

        // Speichere die Verbindung
        $this->connections[$connectionName] = $connection;

        return $connection;
    }

    /**
     * Holt die Standardverbindung
     *
     * @return ConnectionInterface
     */
    public function getDefaultConnection(): ConnectionInterface
    {
        return $this->getConnection($this->defaultConnection);
    }

    /**
     * Schließt eine Verbindung
     *
     * @param string|null $name Name der Verbindung (oder null für alle Verbindungen)
     * @return void
     */
    public function closeConnection(?string $name = null): void
    {
        if ($name === null) {
            // Schließe alle Verbindungen
            foreach ($this->connections as $connection) {
                $connection->disconnect();
            }

            $this->connections = [];
        } elseif (isset($this->connections[$name])) {
            // Schließe die angegebene Verbindung
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }
}
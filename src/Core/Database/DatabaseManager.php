<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Verwaltet Datenbankverbindungen
 */
class DatabaseManager
{
    /**
     * Aktive Verbindungen
     */
    private array $connections = [];

    /**
     * Konfigurationen für Verbindungen
     */
    private array $configs = [];

    /**
     * Standardverbindung
     */
    private string $defaultConnection = 'default';

    /**
     * Konstruktor
     *
     * @param array $configs Konfigurationen für Verbindungen
     * @param string $defaultConnection Standardverbindung
     */
    public function __construct(array $configs = [], string $defaultConnection = 'default')
    {
        $this->configs = $configs;
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * Setzt die Konfigurationen für Verbindungen
     *
     * @param array $configs Konfigurationen für Verbindungen
     * @return self
     */
    public function setConfigs(array $configs): self
    {
        $this->configs = $configs;

        return $this;
    }

    /**
     * Setzt die Standardverbindung
     *
     * @param string $defaultConnection Standardverbindung
     * @return self
     */
    public function setDefaultConnection(string $defaultConnection): self
    {
        $this->defaultConnection = $defaultConnection;

        return $this;
    }

    /**
     * Gibt eine Verbindung zurück
     *
     * @param string|null $name Name der Verbindung oder null für die Standardverbindung
     * @return Connection
     * @throws \Exception wenn die Verbindung nicht konfiguriert ist
     */
    public function connection(?string $name = null): Connection
    {
        $name = $name ?? $this->defaultConnection;

        // Wenn die Verbindung bereits existiert, zurückgeben
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        // Wenn die Verbindung nicht konfiguriert ist, Exception werfen
        if (!isset($this->configs[$name])) {
            throw new \Exception("Datenbankverbindung '{$name}' ist nicht konfiguriert.");
        }

        // Verbindung erstellen
        $this->connections[$name] = new Connection($this->configs[$name]);

        return $this->connections[$name];
    }

    /**
     * Shortcut für connection()->table()
     *
     * @param string $table Tabellenname
     * @param string|null $connection Name der Verbindung oder null für die Standardverbindung
     * @return QueryBuilder
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->connection($connection)->table($table);
    }

    /**
     * Schließt alle aktiven Verbindungen
     *
     * @return void
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }
}
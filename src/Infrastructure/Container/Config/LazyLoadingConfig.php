<?php


declare(strict_types=1);

namespace App\Infrastructure\Container\Config;

class LazyLoadingConfig
{
    /**
     * Liste von Services, die immer lazy geladen werden sollen
     *
     * @var array<string>
     */
    public array $heavyServices = [
        'App\\Infrastructure\\Database\\QueryBuilder',
    ];

    /**
     * Liste von Services, die niemals lazy geladen werden sollen
     *
     * @var array<string>
     */
    public array $excludedServices = [
        'App\\Infrastructure\\Session\\Contracts\\SessionInterface',
    ];

    /**
     * Schwellenwert für Speicherbedarf (in Bytes)
     *
     * @var int
     */
    public int $memoryThreshold = 1024 * 1024; // 1 MB

    /**
     * Maximale Anzahl von Konstruktor-Parametern für automatisches Lazy Loading
     *
     * @var int
     */
    public int $constructorParameterThreshold = 3;

    /**
     * Ob automatische Erkennung von schweren Services aktiviert ist
     *
     * @var bool
     */
    public bool $autoDetectHeavyServices = true;

    /**
     * Minimale Ausführungszeit (in Sekunden), ab der ein Service als "schwer" gilt
     *
     * @var float
     */
    public float $executionTimeThreshold = 0.1; // 100 Millisekunden
}
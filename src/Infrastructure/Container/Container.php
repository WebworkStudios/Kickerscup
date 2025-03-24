<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Contracts\FactoryInterface;
use App\Infrastructure\Container\Exceptions\BindingResolutionException;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\ErrorHandling\Contracts\ExceptionHandlerInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Logging\FileLogger;
use App\Infrastructure\Logging\LoggerConfiguration;
use Closure;
use Throwable;

/**
 * Container-Implementierung
 */
class Container implements ContainerInterface
{
    /**
     * Die registrierten Typ-Bindungen.
     *
     * @var array<string, array{concrete: mixed, shared: bool, scoped: bool}>
     */
    protected array $bindings = [];

    /**
     * Die registrierten Factories.
     *
     * @var array<string, FactoryInterface>
     */
    protected array $factories = [];

    /**
     * Die gemerkten Singleton-Instanzen.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Die gemerkten Scoped-Instanzen.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $scopedInstances = [];

    /**
     * Die aktuelle Scope-ID.
     */
    protected string $currentScopeId = 'default';

    /**
     * Stack zur Erkennung von zirkulären Abhängigkeiten.
     *
     * @var array<string>
     */
    protected array $resolutionStack = [];

    /**
     * Der Reflection-Resolver.
     */
    protected ReflectionResolver $reflectionResolver;

    /**
     * Der integrierte Logger.
     */
    // Beispiel für einen Logger Service
    private ?LoggerInterface $_logger = null;

    public ?LoggerInterface $logger {
        get {
            if ($this->_logger === null && $this->has(LoggerInterface::class)) {
                $this->_logger = $this->get(LoggerInterface::class);
            }
            return $this->_logger;
        }
        set {
            $this->_logger = $value;
            $this->instances[LoggerInterface::class] = $value;
        }
    }

    /**
     * Container-Konstruktor.
     */
    public function __construct()
    {
        $this->reflectionResolver = new ReflectionResolver($this);

        // Registriere den Container selbst als Singleton
        $this->instances[ContainerInterface::class] = $this;
        $this->instances[self::class] = $this;

        // Standardmäßigen Logger initialisieren
        $this->initializeDefaultLogger();
    }

    /**
     * Initialisiert den Standard-Logger
     */
    protected function initializeDefaultLogger(): void
    {
        try {
            // Erstelle eine Standard-Logger-Instanz
            $this->logger = new FileLogger(
                new LoggerConfiguration()
            );

            // Registriere den Logger als Singleton
            $this->instances[LoggerInterface::class] = $this->logger;
        } catch (Throwable $e) {
            // Fallback-Logging, falls Initialisierung fehlschlägt
            error_log('Fehler bei Logger-Initialisierung: ' . $e->getMessage());
        }
    }

    /**
     * Setzt einen benutzerdefinierten Logger
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->instances[LoggerInterface::class] = $logger;
    }

    /**
     * Gibt den aktuellen Logger zurück
     *
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, mixed $concrete = null): static
    {
        // Wenn keine konkrete Implementierung angegeben ist, verwende den abstrakten Typ
        $concrete = $concrete ?? $abstract;

        // Speichere die Bindung
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'scoped' => false,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, mixed $concrete = null): static
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
            'scoped' => false,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function scoped(string $abstract, mixed $concrete = null): static
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'scoped' => true,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function factory(string $abstract, FactoryInterface $factory): static
    {
        $this->factories[$abstract] = $factory;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param string $id
     * @return mixed
     * @throws BindingResolutionException
     * @throws ContainerException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (NotFoundException $e) {
            // Die NotFoundException wird direkt weitergeleitet
            throw $e;
        } catch (BindingResolutionException $e) {
            // Bindungsprobleme werden auch weitergeleitet
            throw $e;
        } catch (Throwable $e) {
            // Andere Fehler werden als ContainerException gekapselt
            $exceptionHandler = $this->has(ExceptionHandlerInterface::class) ?
                $this->resolve(ExceptionHandlerInterface::class) : null;

            if ($exceptionHandler) {
                $exceptionHandler->report($e, ['container_id' => $id]);
            }

            // Logging mit Fallback
            try {
                $this->logger?->error('Container resolution error', [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $loggerError) {
                error_log('Logger-Fehler während Container-Auflösung: ' . $loggerError->getMessage());
            }

            throw new ContainerException(
                "Fehler bei der Auflösung von '$id': " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) ||
            isset($this->instances[$id]) ||
            isset($this->factories[$id]);
    }

    /**
     * {@inheritdoc}
     * @throws ContainerException
     * @throws Throwable
     */
    public function makeWith(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Löst einen Typ auf und gibt eine Instanz zurück.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     * @throws Throwable
     */
    /**
     * Löst einen Typ auf und gibt eine Instanz zurück.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     * @throws Throwable
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        // Logging-Versuch mit Fallback
        try {
            $this->logger?->debug('Resolving dependency', ['abstract' => $abstract]);
        } catch (Throwable $loggerError) {
            error_log('Logger-Fehler bei Dependency Resolution: ' . $loggerError->getMessage());
        }

        // Prüfe auf zirkuläre Abhängigkeit
        if (in_array($abstract, $this->resolutionStack)) {
            // Logging mit Fallback
            try {
                $this->logger?->error('Circular dependency detected', [
                    'stack' => $this->resolutionStack,
                    'current' => $abstract
                ]);
            } catch (Throwable) {
                error_log('Circular dependency: ' . implode(' -> ', $this->resolutionStack) . " -> $abstract");
            }

            throw new BindingResolutionException(
                "Zirkuläre Abhängigkeit erkannt: " .
                implode(' -> ', $this->resolutionStack) . " -> $abstract"
            );
        }

        // Typ zum Auflösung-Stack hinzufügen
        $this->resolutionStack[] = $abstract;

        try {
            // 1. Prüfe auf vorhandene Singleton-Instanz
            if (isset($this->instances[$abstract])) {
                // Typ aus dem Stack entfernen vor Rückgabe
                array_pop($this->resolutionStack);
                return $this->instances[$abstract];
            }

            // 2. Prüfe auf Scoped-Instanz
            if (
                isset($this->bindings[$abstract]['scoped']) &&
                $this->bindings[$abstract]['scoped'] &&
                isset($this->scopedInstances[$this->currentScopeId][$abstract])
            ) {
                // Typ aus dem Stack entfernen vor Rückgabe
                array_pop($this->resolutionStack);
                return $this->scopedInstances[$this->currentScopeId][$abstract];
            }

            // 3. Prüfe auf registrierte Factory
            if (isset($this->factories[$abstract])) {
                // Typ aus dem Stack entfernen vor Rückgabe der Factory-Instanz
                array_pop($this->resolutionStack);
                return $this->factories[$abstract]->make($this, $parameters);
            }

            // 4. Prüfe auf Bindung
            if (!isset($this->bindings[$abstract])) {
                // Wenn keine Bindung existiert, versuche, den Typ direkt aufzulösen
                if (class_exists($abstract)) {
                    $instance = $this->reflectionResolver->resolve($abstract, $parameters);
                    // Typ aus dem Stack entfernen vor Rückgabe
                    array_pop($this->resolutionStack);
                    return $instance;
                }

                // Typ aus dem Stack entfernen vor Exception
                array_pop($this->resolutionStack);
                throw new NotFoundException("Typ $abstract konnte nicht gefunden werden.");
            }

            $concrete = $this->bindings[$abstract]['concrete'];

            // Wenn die konkrete Implementierung ein Closure ist, führe es aus
            if ($concrete instanceof Closure) {
                $instance = $concrete($this, $parameters);
            } elseif (is_string($concrete) && $concrete !== $abstract) {
                // Wenn die konkrete Implementierung ein anderer Typ ist, löse diesen rekursiv auf
                $instance = $this->resolve($concrete, $parameters);
            } else {
                // Sonst versuche, den Typ via Reflection aufzulösen
                $instance = $this->reflectionResolver->resolve($concrete, $parameters);
            }

            // Speichere Singleton-Instanzen
            if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
                $this->instances[$abstract] = $instance;
            }

            // Speichere Scoped-Instanzen
            if (isset($this->bindings[$abstract]['scoped']) && $this->bindings[$abstract]['scoped']) {
                if (!isset($this->scopedInstances[$this->currentScopeId])) {
                    $this->scopedInstances[$this->currentScopeId] = [];
                }
                $this->scopedInstances[$this->currentScopeId][$abstract] = $instance;
            }

            // Typ aus dem Stack entfernen vor Rückgabe
            array_pop($this->resolutionStack);

            // Logging-Versuch mit Fallback
            try {
                $this->logger?->debug('Resolved dependency', ['abstract' => $abstract]);
            } catch (Throwable $loggerError) {
                error_log('Logger-Fehler nach Dependency Resolution: ' . $loggerError->getMessage());
            }

            return $instance;
        } catch (Throwable $e) {
            // Bei Fehlern den Stack zurücksetzen
            $this->resolutionStack = [];
            throw $e;
        }
    }
}
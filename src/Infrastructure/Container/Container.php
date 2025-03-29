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
    public ?LoggerInterface $logger {
        get {
            // Rufe den Logger nur bei Bedarf ab und cache das Ergebnis
            if ($this->_logger === null && $this->has(LoggerInterface::class)) {
                try {
                    // Verwende vorhandene Instance direkt anstatt resolve zu nutzen
                    $this->_logger = $this->instances[LoggerInterface::class] ?? null;

                    // Fallback nur wenn nötig
                    if ($this->_logger === null) {
                        $this->_logger = $this->get(LoggerInterface::class);
                    }
                } catch (Throwable) {
                    // Ignoriere Fehler beim Logger-Abruf, um Endlos-Rekursion zu vermeiden
                    $this->_logger = null;
                }
            }
            return $this->_logger;
        }
        set {
            $this->_logger = $value;
            $this->instances[LoggerInterface::class] = $value;
        }
    }
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

    protected ReflectionResolver $reflectionResolver;

    private ?LoggerInterface $_logger = null;

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
     * Gibt den aktuellen Logger zurück
     *
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
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
     * {@inheritdoc}
     */
    public function bind(string $abstract, mixed $concrete = null): static
    {
        // Wenn eine konkrete Instanz übergeben wurde, speichere sie direkt als Singleton
        if (is_object($concrete) && !$concrete instanceof Closure) {
            $this->instances[$abstract] = $concrete;
            return $this;
        }

        return $this->addBinding($abstract, $concrete, shared: false, scoped: false);
    }

    /**
     * Fügt eine Bindung zum Container hinzu.
     *
     * @param string $abstract Der abstrakte Typ
     * @param mixed $concrete Die konkrete Implementierung
     * @param bool $shared Ob die Implementierung geteilt werden soll (Singleton)
     * @param bool $scoped Ob die Implementierung scoped sein soll
     * @return static
     */
    protected function addBinding(string $abstract, mixed $concrete = null, bool $shared = false, bool $scoped = false): static
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => $shared,
            'scoped' => $scoped,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, mixed $concrete = null): static
    {
        return $this->addBinding($abstract, $concrete, shared: true, scoped: false);
    }

    /**
     * {@inheritdoc}
     */
    public function scoped(string $abstract, mixed $concrete = null): static
    {
        return $this->addBinding($abstract, $concrete, shared: false, scoped: true);
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
     * Löst einen Typ auf und gibt eine Instanz zurück.
     *
     * @param string $abstract Typ oder Identifier aufzulösen
     * @param array $parameters Zusätzliche Parameter für die Konstruktion
     * @return mixed Die aufgelöste Instanz
     * @throws BindingResolutionException Wenn ein Problem bei der Auflösung einer Abhängigkeit auftritt
     * @throws NotFoundException Wenn der angeforderte Typ nicht gefunden wurde
     * @throws ContainerException Bei allgemeinen Container-Fehlern
     * @throws Throwable Bei unerwarteten Fehlern
     */
    // src/Infrastructure/Container/Container.php

// Optimiere die resolve-Methode
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Prüfe auf zirkuläre Abhängigkeit mit effizientem Array-Lookup
        if (in_array($abstract, $this->resolutionStack, true)) {
            $this->logCircularDependency($abstract);
            throw new BindingResolutionException(
                "Zirkuläre Abhängigkeit erkannt: " .
                implode(' -> ', $this->resolutionStack) . " -> $abstract"
            );
        }

        // Schutz gegen zu tiefe Rekursion
        if (count($this->resolutionStack) > 50) {
            throw new BindingResolutionException(
                "Maximale Rekursionstiefe überschritten bei Auflösung von '$abstract'. " .
                "Mögliche zirkuläre Abhängigkeit: " . implode(' -> ', $this->resolutionStack)
            );
        }

        // Typ zum Auflösung-Stack hinzufügen
        $this->resolutionStack[] = $abstract;

        try {
            // Versuche, eine Instanz zu erhalten oder zu erstellen
            $instance = $this->resolveInstance($abstract, $parameters);

            // Typ aus dem Stack entfernen vor Rückgabe
            array_pop($this->resolutionStack);

            // Reduziere Logging - nur bei Debugging
            if ($this->logger && getenv('APP_DEBUG') === 'true') {
                $this->logger->debug('Resolved dependency', ['abstract' => $abstract]);
            }

            return $instance;
        } catch (Throwable $e) {
            // Bei Fehlern den Stack korrigieren und Fehler weiterleiten
            array_pop($this->resolutionStack);
            throw $e;
        }
    }

    /**
     * Loggt eine erkannte zirkuläre Abhängigkeit
     *
     * @param string $abstract Der Typ, der die zirkuläre Abhängigkeit verursacht
     * @return void
     */
    protected function logCircularDependency(string $abstract): void
    {
        try {
            $this->logger?->error('Circular dependency detected', [
                'stack' => $this->resolutionStack,
                'current' => $abstract
            ]);
        } catch (Throwable) {
            error_log('Circular dependency: ' . implode(' -> ', $this->resolutionStack) . " -> $abstract");
        }
    }

    /**
     * Versucht, eine Instanz für den gegebenen Typ zu erhalten oder zu erstellen.
     *
     * @param string $abstract Typ oder Identifier
     * @param array $parameters Zusätzliche Parameter
     * @return mixed Die Instanz
     * @throws BindingResolutionException
     * @throws NotFoundException
     */
    protected function resolveInstance(string $abstract, array $parameters = []): mixed
    {
        // 1. Prüfe auf vorhandene Singleton-Instanz
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Prüfe auf Scoped-Instanz
        $binding = $this->bindings[$abstract] ?? null;
        $isScoped = $binding['scoped'] ?? false;

        if ($isScoped && isset($this->scopedInstances[$this->currentScopeId][$abstract])) {
            return $this->scopedInstances[$this->currentScopeId][$abstract];
        }

        // 3. Prüfe auf registrierte Factory
        if (isset($this->factories[$abstract])) {
            return $this->factories[$abstract]->make($this, $parameters);
        }

        // 4. Erstelle eine neue Instanz
        $instance = $this->createInstance($abstract, $parameters);

        // Speichere Singleton-Instanzen
        $isShared = $binding['shared'] ?? false;
        if ($isShared) {
            $this->instances[$abstract] = $instance;
        }

        // Speichere Scoped-Instanzen
        if ($isScoped) {
            if (!isset($this->scopedInstances[$this->currentScopeId])) {
                $this->scopedInstances[$this->currentScopeId] = [];
            }
            $this->scopedInstances[$this->currentScopeId][$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Erstellt eine neue Instanz für den gegebenen Typ.
     *
     * @param string $abstract Typ oder Identifier
     * @param array $parameters Zusätzliche Parameter
     * @return mixed Die erstellte Instanz
     * @throws BindingResolutionException
     * @throws NotFoundException
     */
    protected function createInstance(string $abstract, array $parameters): mixed
    {
        // Prüfe auf Bindung
        $binding = $this->bindings[$abstract] ?? null;

        // Wenn keine Bindung existiert, versuche, den Typ direkt aufzulösen
        if ($binding === null) {
            if (class_exists($abstract)) {
                return $this->reflectionResolver->resolve($abstract, $parameters);
            }

            throw new NotFoundException("Typ $abstract konnte nicht gefunden werden.");
        }

        $concrete = $binding['concrete'];

        // Wenn die konkrete Implementierung ein Closure ist, führe es aus
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        // Wenn die konkrete Implementierung ein anderer Typ ist, löse diesen rekursiv auf
        if (is_string($concrete) && $concrete !== $abstract) {
            return $this->resolve($concrete, $parameters);
        }

        // Sonst versuche, den Typ via Reflection aufzulösen
        return $this->reflectionResolver->resolve($concrete, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        // Using PHP 8.4's array_any to check if any of the collections has the ID
        return array_any(
            [$this->bindings, $this->instances, $this->factories],
            fn($collection) => isset($collection[$id])
        );
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
}

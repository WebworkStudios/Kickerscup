<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Scoped;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Config\LazyLoadingConfig;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Enums\LifecycleType;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Scanner für automatische Service-Registrierung
 */
class ServiceScanner
{
    /**
     * Der Container.
     */
    protected ContainerInterface $container;

    /**
     * Konfiguration für Lazy Loading
     */
    protected LazyLoadingConfig $lazyLoadingConfig;

    /**
     * Logger für Benachrichtigungen
     */
    protected LoggerInterface $logger;

    /**
     * Konstruktor.
     */
    public function __construct(
        ContainerInterface $container,
        ?LazyLoadingConfig $lazyLoadingConfig = null,
        ?LoggerInterface $logger = null
    ) {
        $this->container = $container;
        $this->lazyLoadingConfig = $lazyLoadingConfig ?? new LazyLoadingConfig();
        try {
            $this->logger = $logger ?? $container->get(LoggerInterface::class);
        } catch (Exceptions\NotFoundException|Exceptions\ContainerException) {

        }
    }

    /**
     * Scannt Verzeichnisse nach injizierbaren Klassen.
     *
     * @param array $directories Zu scannende Verzeichnisse
     * @param string $namespace Der Basis-Namespace
     * @return void
     */
    public function scan(array $directories, string $namespace = ''): void
    {
        $this->logger->info('ServiceScanner: Starting scan', [
            'directories' => $directories,
            'namespace' => $namespace
        ]);

        foreach ($directories as $directory) {
            $this->scanDirectory($directory, $namespace);
        }

        $this->logger->info('ServiceScanner: Scan completed');
    }

    /**
     * Scannt ein Verzeichnis nach injizierbaren Klassen.
     *
     * @param string $directory Das zu scannende Verzeichnis
     * @param string $namespace Der Basis-Namespace
     * @return void
     */
    protected function scanDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            $this->logger->warning('ServiceScanner: Directory not found', ['directory' => $directory]);
            return;
        }

        $this->logger->debug('ServiceScanner: Scanning directory', ['directory' => $directory]);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $scannedFiles = 0;
        $registeredServices = 0;

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $scannedFiles++;
                $className = $this->getClassNameFromFile($file->getPathname(), $namespace);

                if ($className) {
                    $this->registerClassIfInjectable($className);
                    $registeredServices++;
                }
            }
        }

        $this->logger->info('ServiceScanner: Directory scan completed', [
            'directory' => $directory,
            'scanned_files' => $scannedFiles,
            'registered_services' => $registeredServices
        ]);
    }

    /**
     * Ermittelt den Klassennamen aus einem Dateipfad
     *
     * @param string $filePath Der Dateipfad
     * @param string $namespace Der Basis-Namespace
     * @return string|null Der vollständige Klassenname oder null
     */
    protected function getClassNameFromFile(string $filePath, string $namespace): ?string
    {
        $filename = basename($filePath, '.php');

        // Spezielle Behandlung für den Namespace
        if ($namespace === 'App\\Presentation' && (
                str_contains($filePath, '/Presentation/Actions') ||
                str_contains($filePath, '\\Presentation\\Actions')
            )) {
            $path = $filePath;
            $pathFragments = ['/Actions/Users/', '\\Actions\\Users\\'];

            if (array_any($pathFragments, fn($fragment) => str_contains($path, $fragment))) {
                $fullClassName = 'App\\Presentation\\Actions\\Users\\' . $filename;
            } else {
                $fullClassName = 'App\\Presentation\\Actions\\' . $filename;
            }

            if (class_exists($fullClassName)) {
                return $fullClassName;
            }
        }

        // Versuche alternativen Ansatz mit PSR-4-Konventionen
        $srcPos = stripos($filePath, 'src');
        if ($srcPos !== false) {
            $pathAfterSrc = substr($filePath, $srcPos + 4);
            $pathAfterSrc = str_replace(['\\', '/'], '\\', $pathAfterSrc);
            $pathParts = explode('\\', trim($pathAfterSrc, '\\'));

            $lastIndex = count($pathParts) - 1;
            $pathParts[$lastIndex] = basename($pathParts[$lastIndex], '.php');

            $psr4Namespace = 'App\\' . implode('\\', $pathParts);

            if (class_exists($psr4Namespace)) {
                return $psr4Namespace;
            }
        }

        // Fallback mit ursprünglichem Namespace
        $classToTry = $namespace . '\\' . $filename;
        if (class_exists($classToTry)) {
            return $classToTry;
        }

        return null;
    }

    /**
     * Registriert eine Klasse, wenn sie injizierbar ist
     *
     * @param string $className Der Klassenname
     * @return void
     */
    protected function registerClassIfInjectable(string $className): void
    {
        if ($className === "App\\Infrastructure\\Application\\Application") {
            return;
        }
        try {
            $reflector = new ReflectionClass($className);

            // Prüfe auf das Injectable-Attribut
            $injectableAttributes = $reflector->getAttributes(Injectable::class);

            if (empty($injectableAttributes)) {
                return;
            }

            // Hol die Instance des Injectable-Attributs
            $injectableAttribute = $injectableAttributes[0]->newInstance();

            // Bestimme den Alias (wenn vorhanden)
            $abstract = $injectableAttribute->alias ?? $className;

            // Ermittle den Lifecycle basierend auf den Attributen
            $lifecycleType = $this->determineLifecycleType($reflector);

            // Lazy-Loading für besonders aufwändige Services implementieren
            $isHeavyService = $this->isHeavyService($className);

            if ($isHeavyService) {
                $this->registerLazyService($abstract, $className, $lifecycleType);
                $this->logger->info('Registered lazy service', [
                    'service' => $abstract,
                    'reason' => 'Heavy service detection'
                ]);
                return;
            }

            // Registriere entsprechend dem Lifecycle
            match ($lifecycleType) {
                LifecycleType::Singleton => $this->container->singleton($abstract, $className),
                LifecycleType::Scoped => $this->container->scoped($abstract, $className),
                LifecycleType::Transient => $this->container->bind($abstract, $className),
            };
        } catch (ReflectionException $e) {
            $this->logger->warning('Reflection error during service registration', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ermittelt den Lifecycle-Typ einer Klasse basierend auf ihren Attributen
     *
     * @param ReflectionClass $reflector
     * @return LifecycleType
     */
    protected function determineLifecycleType(ReflectionClass $reflector): LifecycleType
    {
        if (!empty($reflector->getAttributes(Singleton::class))) {
            return LifecycleType::Singleton;
        }

        if (!empty($reflector->getAttributes(Scoped::class))) {
            return LifecycleType::Scoped;
        }

        return LifecycleType::Transient;

        // Standard ist Transient
    }

    /**
     * Prüft, ob ein Service als "schwer" eingestuft werden soll
     *
     * @param string $className Der zu prüfende Klassenname
     * @return bool
     */
    private function isHeavyService(string $className): bool
    {
        // Vorregistrierte schwere Services - schnellster Check zuerst
        if (in_array($className, $this->lazyLoadingConfig->heavyServices)) {
            return true;
        }

        // Komplexitätsüberprüfung nur ausführen, wenn Auto-Detection aktiviert ist
        if (!$this->lazyLoadingConfig->autoDetectHeavyServices) {
            return false;
        }

        try {
            // Prüfe zuerst Konstruktorkomplexität, da dies am wenigsten Ressourcen benötigt
            if ($this->hasComplexConstructor($className)) {
                return true;
            }

            // Teurere Überprüfungen nur durchführen, wenn notwendig
            return $this->hasLongExecutionTime($className) ||
                $this->hasHighMemoryFootprint($className);
        } catch (Throwable $e) {
            $this->logger->warning('Error detecting heavy service', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Prüft die Komplexität des Konstruktors
     *
     * @param string $className Der zu prüfende Klassenname
     * @return bool
     */
    private function hasComplexConstructor(string $className): bool
    {
        try {
            $reflector = new ReflectionClass($className);
            $constructor = $reflector->getConstructor();

            return $constructor &&
                $constructor->getNumberOfParameters() > $this->lazyLoadingConfig->constructorParameterThreshold;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Prüft den Speicherbedarf der Klasse
     *
     * @param string $className Der zu prüfende Klassenname
     * @return bool
     */
    private function hasHighMemoryFootprint(string $className): bool
    {
        $memoryBefore = memory_get_usage();
        try {
            $instance = new $className();
            $memoryAfter = memory_get_usage();

            return ($memoryAfter - $memoryBefore) > $this->lazyLoadingConfig->memoryThreshold;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Prüft die Ausführungszeit der Klasse
     *
     * @param string $className Der zu prüfende Klassenname
     * @return bool
     */
    private function hasLongExecutionTime(string $className): bool
    {
        $startTime = microtime(true);
        try {
            $instance = new $className();
            $duration = microtime(true) - $startTime;

            return $duration > $this->lazyLoadingConfig->executionTimeThreshold;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Registriert einen Service für Lazy Loading
     *
     * @param string $abstract Abstraktion/Alias des Services
     * @param string $concrete Konkrete Implementierung
     * @param LifecycleType $lifecycleType Lifecycle-Typ
     * @return void
     */
    private function registerLazyService(string $abstract, string $concrete, LifecycleType $lifecycleType): void
    {
        $factory = function ($container) use ($concrete) {
            return $container->makeWith($concrete);
        };

        match ($lifecycleType) {
            LifecycleType::Singleton => $this->container->singleton($abstract, $factory),
            LifecycleType::Scoped => $this->container->scoped($abstract, $factory),
            LifecycleType::Transient => $this->container->bind($abstract, $factory),
        };
    }
}
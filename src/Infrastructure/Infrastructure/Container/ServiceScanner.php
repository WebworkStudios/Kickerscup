<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Scoped;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Attributes\Transient;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;

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
     * Konstruktor.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
        foreach ($directories as $directory) {
            $this->scanDirectory($directory, $namespace);
        }
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
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname(), $namespace);

                if ($className) {
                    $this->registerClassIfInjectable($className);
                }
            }
        }
    }

    /**
     * Ermittelt den Klassennamen aus einem Dateipfad.
     *
     * @param string $filePath Der Dateipfad
     * @param string $namespace Der Basis-Namespace
     * @return string|null Der vollständige Klassenname oder null
     */
    protected function getClassNameFromFile(string $filePath, string $namespace): ?string
    {
        // Extrahiere den relativen Pfad
        $relativePath = str_replace(
            [dirname($filePath) . DIRECTORY_SEPARATOR, '.php'],
            '',
            $filePath
        );

        // Konstruiere den Klassennamen basierend auf Namespace und Dateipfad
        $className = $namespace . '\\' . str_replace('/', '\\', $relativePath);

        // Prüfe, ob die Klasse existiert
        if (class_exists($className)) {
            return $className;
        }

        return null;
    }

    /**
     * Registriert eine Klasse im Container, wenn sie injizierbar ist.
     *
     * @param string $className Der Klassenname
     * @return void
     */
    protected function registerClassIfInjectable(string $className): void
    {
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
            $hasSingleton = !empty($reflector->getAttributes(Singleton::class));
            $hasScoped = !empty($reflector->getAttributes(Scoped::class));
            $hasTransient = !empty($reflector->getAttributes(Transient::class));

            // Registriere entsprechend dem Lifecycle
            if ($hasSingleton) {
                $this->container->singleton($abstract, $className);
            } elseif ($hasScoped) {
                $this->container->scoped($abstract, $className);
            } elseif ($hasTransient) {
                $this->container->bind($abstract, $className);
            } else {
                // Standard ist Transient
                $this->container->bind($abstract, $className);
            }

            // Registriere auch Interfaces, die die Klasse implementiert
            foreach ($reflector->getInterfaces() as $interface) {
                // Nur registrieren, wenn kein expliziter Alias angegeben wurde
                if ($injectableAttribute->alias === null) {
                    $this->container->bind($interface->getName(), $className);
                }
            }
        } catch (ReflectionException $e) {
            // Ignoriere Reflection-Fehler und mache mit dem nächsten weiter
        }
    }
}
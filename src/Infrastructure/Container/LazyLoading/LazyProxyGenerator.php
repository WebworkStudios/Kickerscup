<?php

declare(strict_types=1);

namespace App\Infrastructure\Container\LazyLoading;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * Generator für Lazy-Loading-Proxies
 */
class LazyProxyGenerator
{
    /**
     * Basis-Klasse für alle generierten Proxies
     */
    private const string PROXY_BASE_CLASS = LazyProxyBase::class;

    /**
     * Cache für erzeugte Proxies
     *
     * @var array<string, callable>
     */
    private array $proxyFactories = [];

    /**
     * Konstruktor
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?LoggerInterface   $logger = null
    )
    {
    }

    /**
     * Erstellt einen Lazy-Loading-Proxy für eine Klasse
     *
     * @param string $className Der Name der originalen Klasse
     * @return object Der erstellte Proxy
     */
    public function createProxy(string $className): object
    {
        // Cache prüfen
        if (!isset($this->proxyFactories[$className])) {
            $this->generateProxyFactory($className);
        }

        // Factory ausführen, um Proxy zu erstellen
        return $this->proxyFactories[$className]($this->container, $className);
    }

    /**
     * Generiert eine Factory-Funktion für einen Proxy
     */
    private function generateProxyFactory(string $className): void
    {
        $reflector = new ReflectionClass($className);

        if ($reflector->isInterface() || $reflector->isAbstract()) {
            throw new RuntimeException(
                "Kann keinen Proxy für Interface oder abstrakte Klasse erstellen: $className"
            );
        }

        // Erstelle Factory-Closure
        $this->proxyFactories[$className] = function (ContainerInterface $container, string $targetClass) use ($className) {
            return new LazyProxyBase($container, $targetClass);
        };

        $this->logger?->debug("LazyProxy Factory generiert für: $className");
    }
}
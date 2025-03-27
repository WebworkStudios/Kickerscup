<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Scoped;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Routing\Attributes\Cors;
use App\Infrastructure\Routing\Attributes\Redirect;
use App\Infrastructure\Routing\Attributes\Route;
use App\Infrastructure\Routing\Attributes\RouteParam;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\RouteScannerInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
use App\Infrastructure\Security\Csrf\Attributes\CsrfProtection;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Scanner für automatische Routen-Registrierung
 */
#[Injectable]
#[Scoped]
class RouteScanner implements RouteScannerInterface
{
    /**
     * Konstruktor
     *
     * @param RouterInterface $router Der Router
     * @param UrlGeneratorInterface $urlGenerator Der URL-Generator
     * @param LoggerInterface $logger Der Logger
     * @param ContainerInterface $container Der Container
     */
    // Ändern Sie den Konstruktor
    public function __construct(
        protected RouterInterface       $router,
        protected UrlGeneratorInterface $urlGenerator,
        protected LoggerInterface       $logger,
        protected ContainerInterface    $container
    )
    {
        // Entfernen Sie die Abhängigkeit von RouteScannerInterface
    }

    /**
     * {@inheritdoc}
     */
    public function scan(array $directories, string $namespace = ''): void
    {
        $this->logger->info('RouteScanner: Starting scan', [
            'directories' => $directories,
            'namespace' => $namespace
        ]);

        foreach ($directories as $directory) {
            $this->scanDirectory($directory, $namespace);
        }

        $this->logger->info('RouteScanner: Scan completed');
    }

    /**
     * Scannt ein Verzeichnis nach Routen-Attributen
     *
     * @param string $directory Das zu scannende Verzeichnis
     * @param string $namespace Der Basis-Namespace
     * @return void
     */
    protected function scanDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            $this->logger->warning('RouteScanner: Directory not found', ['directory' => $directory]);
            return;
        }

        $this->logger->debug('RouteScanner: Scanning directory', ['directory' => $directory]);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        $scannedFiles = 0;
        $foundRoutes = 0;

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $scannedFiles++;
                $className = $this->getClassNameFromFile($file->getPathname(), $namespace);

                if ($className) {
                    $routeCount = $this->registerClassRoutes($className);
                    $foundRoutes += $routeCount;

                    if ($routeCount > 0) {
                        $this->logger->debug('RouteScanner: Found routes in class', [
                            'class' => $className,
                            'count' => $routeCount
                        ]);
                    }
                }
            }
        }

        $this->logger->info('RouteScanner: Directory scan completed', [
            'directory' => $directory,
            'scanned_files' => $scannedFiles,
            'found_routes' => $foundRoutes
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

        // Logger für Debug-Zwecke
        $this->logger->debug('RouteScanner: Processing file', [
            'file' => $filePath,
            'base_namespace' => $namespace
        ]);

        // Spezielle Behandlung für den Namespace
        if ($namespace === 'App\\Presentation' && (
                str_contains($filePath, '/Presentation/Actions') ||
                str_contains($filePath, '\\Presentation\\Actions')
            )) {

// Mit array_any kann das verbessert werden:
            $path = $filePath;
            $pathFragments = ['/Actions/Users/', '\\Actions\\Users\\'];
            if (array_any($pathFragments, fn($fragment) => str_contains($path, $fragment))) {
                $fullClassName = 'App\\Presentation\\Actions\\Users\\' . $filename;
            } else {
                $fullClassName = 'App\\Presentation\\Actions\\' . $filename;
            }

            $this->logger->debug('RouteScanner: Using direct namespace mapping', [
                'file' => $filePath,
                'attempted_class' => $fullClassName
            ]);

            // Überprüfe, ob die Klasse existiert
            if (class_exists($fullClassName)) {
                $this->logger->debug('RouteScanner: Class found', ['class' => $fullClassName]);
                return $fullClassName;
            }
        }

        // Versuche alternativen Ansatz mit PSR-4-Konventionen
        // Extrahiere Teile nach "src/"
        $srcPos = stripos($filePath, 'src');
        if ($srcPos !== false) {
            $pathAfterSrc = substr($filePath, $srcPos + 4); // +4 für 'src/'
            $pathAfterSrc = str_replace(['\\', '/'], '\\', $pathAfterSrc);
            $pathParts = explode('\\', trim($pathAfterSrc, '\\'));

            // Entferne die PHP-Erweiterung vom letzten Teil
            $lastIndex = count($pathParts) - 1;
            $pathParts[$lastIndex] = basename($pathParts[$lastIndex], '.php');

            // PSR-4 Namespace
            $psr4Namespace = 'App\\' . implode('\\', $pathParts);

            $this->logger->debug('RouteScanner: Trying PSR-4 namespace', ['class' => $psr4Namespace]);

            if (class_exists($psr4Namespace)) {
                $this->logger->debug('RouteScanner: PSR-4 class found', ['class' => $psr4Namespace]);
                return $psr4Namespace;
            }
        }

        // Versuche es als letzten Ausweg mit dem ursprünglichen Namespace
        $classToTry = $namespace . '\\' . $filename;
        if (class_exists($classToTry)) {
            $this->logger->debug('RouteScanner: Found with original namespace', ['class' => $classToTry]);
            return $classToTry;
        }

        $this->logger->debug('RouteScanner: Class not found in any namespace', [
            'file' => $filePath
        ]);

        return null;
    }

    /**
     * Registriert Routen für eine Klasse
     *
     * @param string $className Der Klassenname
     * @return int Anzahl der registrierten Routen
     */
    protected function registerClassRoutes(string $className): int
    {
        $routeCount = 0;

        try {
            $reflector = new ReflectionClass($className);

            // Prüfe, ob die Klasse ein __invoke hat (Action)
            $hasInvokeMethod = $reflector->hasMethod('__invoke');

            // Sammle alle Route-Attribute der Klasse
            $classRouteAttributes = $reflector->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

            // Sammle CORS-Attribute der Klasse
            $classCorsAttributes = $reflector->getAttributes(Cors::class);
            $corsConfig = !empty($classCorsAttributes) ? $classCorsAttributes[0]->newInstance() : null;

            // Sammle Redirect-Attribute der Klasse
            $redirectAttributes = $reflector->getAttributes(Redirect::class);

            // Registriere Redirects aus den Attributen
            foreach ($redirectAttributes as $attribute) {
                /** @var Redirect $redirectAttr */
                $redirectAttr = $attribute->newInstance();
                $this->router->addRedirect(
                    $redirectAttr->fromPath,
                    $redirectAttr->toPath,
                    $redirectAttr->statusCode,
                    $redirectAttr->preserveQueryString
                );
                $routeCount++;
            }

            // Wenn die Klasse __invoke hat und Route-Attribute besitzt
            if ($hasInvokeMethod && !empty($classRouteAttributes)) {
                $invokeMethod = $reflector->getMethod('__invoke');

                foreach ($classRouteAttributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();
                    $path = $routeAttribute->path;
                    $methods = $routeAttribute->methods;
                    $name = $routeAttribute->name;
                    $domain = $routeAttribute->domain;

                    // Registriere die Route mit der Klasse als Handler und Domain
                    $this->router->addRoute($methods, $path, $className, $name, $domain);
                    $routeCount++;

                    $this->logger->debug('RouteScanner: Registered class route', [
                        'class' => $className,
                        'path' => $path,
                        'methods' => is_array($methods) ? implode(',', $methods) : $methods,
                        'name' => $name
                    ]);

                    // Registriere CORS-Konfiguration, wenn vorhanden
                    if ($corsConfig) {
                        $this->router->addCorsConfiguration($path, $corsConfig);
                    }

                    $csrfAttributes = $invokeMethod->getAttributes(CsrfProtection::class);
                    if (!empty($csrfAttributes)) {
                        $csrfConfig = $csrfAttributes[0]->newInstance();
                        $this->router->addCsrfConfiguration($path, $csrfConfig);
                    }

                    // Sammle Parameter-Attribute für die URL-Generierung
                    $this->collectParameterAttributes($invokeMethod, $path, $name, $domain);
                }
            }

            // Scanne auch Methoden nach Route-Attributen
            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Überspringe Konstruktor und magische Methoden
                if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $methodRouteAttributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);
                if (empty($methodRouteAttributes)) {
                    continue;
                }

                // Überprüfe CORS-Attribute der Methode (überschreiben die Klassenattribute)
                $methodCorsAttributes = $method->getAttributes(Cors::class);
                $methodCorsConfig = !empty($methodCorsAttributes)
                    ? $methodCorsAttributes[0]->newInstance()
                    : $corsConfig; // Verwende Klassen-CORS, wenn keine Methoden-CORS

                // Überprüfe Redirect-Attribute der Methode
                $methodRedirectAttrs = $method->getAttributes(Redirect::class);

                foreach ($methodRedirectAttrs as $attribute) {
                    /** @var Redirect $redirectAttr */
                    $redirectAttr = $attribute->newInstance();
                    $this->router->addRedirect(
                        $redirectAttr->fromPath,
                        $redirectAttr->toPath,
                        $redirectAttr->statusCode,
                        $redirectAttr->preserveQueryString
                    );
                    $routeCount++;
                }

                foreach ($methodRouteAttributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();
                    $path = $routeAttribute->path;
                    $methods = $routeAttribute->methods;
                    $name = $routeAttribute->name;
                    $domain = $routeAttribute->domain;

                    // Registriere die Route mit der Klasse und Methode als Handler und Domain
                    $this->router->addRoute($methods, $path, [$className, $method->getName()], $name, $domain);
                    $routeCount++;

                    $this->logger->debug('RouteScanner: Registered method route', [
                        'class' => $className,
                        'method' => $method->getName(),
                        'path' => $path,
                        'methods' => is_array($methods) ? implode(',', $methods) : $methods,
                        'name' => $name
                    ]);

                    if ($methodCorsConfig) {
                        $this->router->addCorsConfiguration($path, $methodCorsConfig);
                    }

                    // Sammle CSRF-Attribute der Methode
                    $csrfAttributes = $method->getAttributes(CsrfProtection::class);
                    if (!empty($csrfAttributes)) {
                        $csrfConfig = $csrfAttributes[0]->newInstance();
                        $this->router->addCsrfConfiguration($path, $csrfConfig);
                    }

                    // Sammle Parameter-Attribute für die URL-Generierung
                    $this->collectParameterAttributes($method, $path, $name, $domain);
                }
            }
        } catch (ReflectionException $e) {
            $this->logger->error('RouteScanner: Reflection error', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
        } catch (Throwable $e) {
            $this->logger->error('RouteScanner: Unexpected error', [
                'class' => $className,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $routeCount;
    }

    /**
     * Sammelt Parameter-Attribute für eine Methode
     *
     * @param ReflectionMethod $method Die Methode
     * @param string $path Der Pfad der Route
     * @param string|null $name Der Name der Route
     * @param string|null $domain Die Domain der Route
     * @return void
     */
    protected function collectParameterAttributes(ReflectionMethod $method, string $path, ?string $name, ?string $domain = null): void
    {
        if ($name === null) {
            return;
        }

        $parameterInfo = [];
        $domainParameterInfo = [];

        // Extrahiere Parameter aus dem Pfad (z.B. {id})
        preg_match_all('#{([^:}]+)(?::([^}]+))?}#', $path, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $paramName = $match[1];
            $regex = $match[2] ?? '[^/]+';

            $parameterInfo[$paramName] = [
                'name' => $paramName,
                'regex' => $regex
            ];
        }

        // Extrahiere Parameter aus der Domain (z.B. {subdomain})
        if ($domain !== null) {
            preg_match_all('#{([^:}]+)(?::([^}]+))?}#', $domain, $domainMatches, PREG_SET_ORDER);

            foreach ($domainMatches as $match) {
                $paramName = $match[1];
                $regex = $match[2] ?? '[^.]+';

                $domainParameterInfo[$paramName] = [
                    'name' => $paramName,
                    'regex' => $regex,
                    'isDomain' => true
                ];
            }
        }

        // Überprüfe Parameter-Attribute
        foreach ($method->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (!array_any([$parameterInfo, $domainParameterInfo], fn($info) => isset($info[$paramName]))) {
                continue;
            }

            $routeParam = array_find($parameter->getAttributes(RouteParam::class), fn() => true)?->newInstance();
            if ($routeParam !== null) {

                // Aktualisiere Parameter für Pfad oder Domain
                if (isset($parameterInfo[$paramName])) {
                    // Pfad-Parameter aktualisieren
                    if ($routeParam->regex !== null) {
                        $parameterInfo[$paramName]['regex'] = $routeParam->regex;
                    }
                    $parameterInfo[$paramName]['optional'] = $routeParam->optional;
                    if ($routeParam->default !== null) {
                        $parameterInfo[$paramName]['default'] = $routeParam->default;
                    }
                } else if (isset($domainParameterInfo[$paramName])) {
                    // Domain-Parameter aktualisieren
                    if ($routeParam->regex !== null) {
                        $domainParameterInfo[$paramName]['regex'] = $routeParam->regex;
                    }
                    $domainParameterInfo[$paramName]['optional'] = $routeParam->optional;
                    if ($routeParam->default !== null) {
                        $domainParameterInfo[$paramName]['default'] = $routeParam->default;
                    }
                }
            }
        }

        // Füge Route-Infos zum URL-Generator hinzu
        $routeInfo = [
            'path' => $path,
            'parameters' => $parameterInfo,
            'domain' => $domain,
            'domainParameters' => $domainParameterInfo
        ];

        $this->urlGenerator->addNamedRoute($name, $routeInfo);
    }
}
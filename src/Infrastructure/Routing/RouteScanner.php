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
    public function __construct(
        protected RouterInterface       $router,
        protected UrlGeneratorInterface $urlGenerator,
        protected LoggerInterface       $logger,
        protected ContainerInterface    $container
    )
    {
    }

    /**
     * @param array $directories
     * @param string $namespace
     * @return void
     */
    public function scan(array $directories, string $namespace = ''): void
    {
        $cacheFile = $this->getCacheFilePath();

        // Cache verwenden, wenn vorhanden
        if (file_exists($cacheFile)) {
            $cachedRoutes = require $cacheFile;
            if (is_array($cachedRoutes)) {
                foreach ($cachedRoutes as $routeInfo) {
                    $this->router->addRoute(
                        $routeInfo['methods'],
                        $routeInfo['path'],
                        $routeInfo['handler'],
                        $routeInfo['name'] ?? null,
                        $routeInfo['domain'] ?? null
                    );

                    // CORS-Konfiguration hinzufügen, wenn vorhanden
                    if (isset($routeInfo['cors'])) {
                        $this->router->addCorsConfiguration($routeInfo['path'], $routeInfo['cors']);
                    }

                    // CSRF-Konfiguration hinzufügen, wenn vorhanden
                    if (isset($routeInfo['csrf'])) {
                        $this->router->addCsrfConfiguration($routeInfo['path'], $routeInfo['csrf']);
                    }
                }
                $this->logger->info('RouteScanner: Routes loaded from cache', [
                    'count' => count($cachedRoutes)
                ]);
                return;
            }
        }

        $this->logger->info('RouteScanner: Starting scan', [
            'directories' => $directories,
            'namespace' => $namespace
        ]);

        // Cache der gefundenen Routen für späteres Speichern
        $routesToCache = [];

        foreach ($directories as $directory) {
            $routesToCache = array_merge(
                $routesToCache,
                $this->scanDirectory($directory, $namespace)
            );
        }

        // Cache schreiben
        $this->writeRouteCache($cacheFile, $routesToCache);

        $this->logger->info('RouteScanner: Scan completed', [
            'routes_found' => count($routesToCache),
            'cache_written' => true
        ]);
    }

    /**
     * Gibt den Pfad zur Cache-Datei zurück
     *
     * @return string
     */
    private function getCacheFilePath(): string
    {
        $cacheDir = APP_ROOT . '/cache';

        // Erstelle Cache-Verzeichnis, falls nicht vorhanden
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return $cacheDir . '/routes.php';
    }

    /**
     * Scannt ein Verzeichnis nach Routen-Attributen und gibt gefundene Routen zurück
     *
     * @param string $directory Das zu scannende Verzeichnis
     * @param string $namespace Der Basis-Namespace
     * @return array Gefundene Routen
     */
    protected function scanDirectory(string $directory, string $namespace): array
    {
        $foundRoutes = [];

        if (!is_dir($directory)) {
            $this->logger->warning('RouteScanner: Directory not found', ['directory' => $directory]);
            return $foundRoutes;
        }

        $this->logger->debug('RouteScanner: Scanning directory', ['directory' => $directory]);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname(), $namespace);

                if ($className) {
                    $classRoutes = $this->registerClassRoutes($className);
                    if (!empty($classRoutes)) {
                        $foundRoutes = array_merge($foundRoutes, $classRoutes);
                    }
                }
            }
        }

        return $foundRoutes;
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
     * Registriert die Routen einer Klasse
     *
     * @param string $className Der Klassenname
     * @return array Der Cache der registrierten Routen
     */
    protected function registerClassRoutes(string $className): array
    {
        $routeCache = [];

        try {
            $reflector = new ReflectionClass($className);

            // Prüfe, ob die Klasse ein __invoke hat (Action)
            $hasInvokeMethod = $reflector->hasMethod('__invoke');

            // Sammle alle Route-Attribute der Klasse
            $classRouteAttributes = $reflector->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

            // Sammle CORS-Attribute der Klasse
            $classCorsAttributes = $reflector->getAttributes(Cors::class);
            $corsConfig = !empty($classCorsAttributes) ? $classCorsAttributes[0]->newInstance() : null;

            // Registriere Redirect-Attribute
            $redirectAttributes = $reflector->getAttributes(Redirect::class);
            foreach ($redirectAttributes as $attribute) {
                $redirectAttr = $attribute->newInstance();
                $this->registerRedirect($redirectAttr, $routeCache);
            }

            // Wenn die Klasse __invoke hat und Route-Attribute besitzt
            if ($hasInvokeMethod && !empty($classRouteAttributes)) {
                $invokeMethod = $reflector->getMethod('__invoke');

                foreach ($classRouteAttributes as $attribute) {
                    $routeAttribute = $attribute->newInstance();
                    $this->registerRouteFromAttribute(
                        $routeAttribute,
                        $className,
                        $corsConfig,
                        $invokeMethod,
                        $routeCache
                    );
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
                    $redirectAttr = $attribute->newInstance();
                    $this->registerRedirect($redirectAttr, $routeCache);
                }

                foreach ($methodRouteAttributes as $attribute) {
                    $routeAttribute = $attribute->newInstance();
                    $this->registerRouteFromAttribute(
                        $routeAttribute,
                        [$className, $method->getName()],
                        $methodCorsConfig,
                        $method,
                        $routeCache
                    );
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

        return $routeCache;
    }

    /**
     * Registriert ein Redirect und fügt es zum Cache hinzu
     *
     * @param Redirect $redirectAttr Das Redirect-Attribut
     * @param array &$routeCache Der Route-Cache
     * @return void
     */
    private function registerRedirect(Redirect $redirectAttr, array &$routeCache): void
    {
        $this->router->addRedirect(
            $redirectAttr->fromPath,
            $redirectAttr->toPath,
            $redirectAttr->statusCode,
            $redirectAttr->preserveQueryString
        );

        // Cache-Eintrag für Redirect
        $routeCache[] = [
            'type' => 'redirect',
            'from_path' => $redirectAttr->fromPath,
            'to_path' => $redirectAttr->toPath,
            'status_code' => $redirectAttr->statusCode,
            'preserve_query_string' => $redirectAttr->preserveQueryString
        ];
    }

    /**
     * Registriert eine Route aus einem Attribut
     *
     * @param Route $routeAttribute Das Route-Attribut
     * @param string|array $handler Der Handler (Klasse oder [Klasse, Methode])
     * @param Cors|null $corsConfig Die CORS-Konfiguration
     * @param ReflectionMethod $method Die Methode für Parameter-Attribute
     * @param array &$routeCache Der Route-Cache
     * @return void
     */
    private function registerRouteFromAttribute(
        Route            $routeAttribute,
        mixed            $handler,
        ?Cors            $corsConfig,
        ReflectionMethod $method,
        array            &$routeCache
    ): void
    {
        $path = $routeAttribute->path;
        $methods = $routeAttribute->methods;
        $name = $routeAttribute->name;
        $domain = $routeAttribute->domain;

        // Registriere die Route
        $this->router->addRoute($methods, $path, $handler, $name, $domain);

        // Cache-Eintrag für Route
        $routeCache[] = [
            'type' => 'route',
            'methods' => $methods,
            'path' => $path,
            'handler' => $handler,
            'name' => $name,
            'domain' => $domain
        ];

        $this->logger->debug('RouteScanner: Registered route', [
            'path' => $path,
            'methods' => is_array($methods) ? implode(',', $methods) : $methods,
            'name' => $name
        ]);

        // Registriere CORS-Konfiguration, wenn vorhanden
        if ($corsConfig) {
            $this->router->addCorsConfiguration($path, $corsConfig);

            // Cache-Eintrag für CORS
            $routeCache[count($routeCache) - 1]['cors'] = [
                'allowOrigin' => $corsConfig->allowOrigin,
                'allowMethods' => $corsConfig->allowMethods,
                'allowHeaders' => $corsConfig->allowHeaders,
                'allowCredentials' => $corsConfig->allowCredentials,
                'maxAge' => $corsConfig->maxAge,
                'exposeHeaders' => $corsConfig->exposeHeaders
            ];
        }

        // Sammle CSRF-Attribute
        $csrfAttributes = $method->getAttributes(CsrfProtection::class);
        if (!empty($csrfAttributes)) {
            $csrfConfig = $csrfAttributes[0]->newInstance();
            $this->router->addCsrfConfiguration($path, $csrfConfig);

            // Cache-Eintrag für CSRF
            $routeCache[count($routeCache) - 1]['csrf'] = [
                'enabled' => $csrfConfig->enabled,
                'tokenKey' => $csrfConfig->tokenKey,
                'validateOrigin' => $csrfConfig->validateOrigin
            ];
        }

        // Sammle Parameter-Attribute für die URL-Generierung
        $this->collectParameterAttributes($method, $path, $name, $domain);
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

            $attrs = $parameter->getAttributes(RouteParam::class);
            $routeParam = !empty($attrs) ? $attrs[0]->newInstance() : null;

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

    /**
     * Schreibt den Route-Cache in eine Datei
     *
     * @param string $cacheFile Pfad zur Cache-Datei
     * @param array $routes Zu cachende Routen
     * @return void
     */
    private function writeRouteCache(string $cacheFile, array $routes): void
    {
        $content = "<?php\n// Generated Route Cache - " . date('Y-m-d H:i:s') . "\nreturn " .
            var_export($routes, true) . ";\n";

        $success = file_put_contents($cacheFile, $content) !== false;

        if (!$success) {
            $this->logger->warning('Failed to write route cache file', ['file' => $cacheFile]);
        }
    }
}
<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Scoped;
use App\Infrastructure\Routing\Attributes\Cors;
use App\Infrastructure\Routing\Attributes\Redirect;
use App\Infrastructure\Routing\Attributes\Route;
use App\Infrastructure\Routing\Attributes\RouteParam;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\RouteScannerInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
use App\Infrastructure\Security\Csrf\Attributes\CsrfProtection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

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
     */
    public function __construct(
        protected RouterInterface       $router,
        protected UrlGeneratorInterface $urlGenerator
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function scan(array $directories, string $namespace = ''): void
    {
        foreach ($directories as $directory) {
            $this->scanDirectory($directory, $namespace);
        }
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
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname(), $namespace);

                if ($className) {
                    $this->registerClassRoutes($className);
                }
            }
        }
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
     * Registriert Routen für eine Klasse
     *
     * @param string $className Der Klassenname
     * @return void
     */
    protected function registerClassRoutes(string $className): void
    {
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

                    // Registriere CORS-Konfiguration, wenn vorhanden
                    $this->addCorsConfigurationIfExists($path, $corsConfig);

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

                    $this->addCorsConfigurationIfExists($path, $methodCorsConfig);

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
        } catch (ReflectionException) {
            // Ignoriere Reflection-Fehler und mache mit dem nächsten weiter
        }
    }

    /**
     * Fügt eine CORS-Konfiguration für einen Pfad hinzu, wenn vorhanden
     *
     * @param string $path Der Pfad der Route
     * @param Cors|null $corsConfig Die CORS-Konfiguration
     * @return void
     */
    private function addCorsConfigurationIfExists(string $path, ?Cors $corsConfig): void
    {
        if ($corsConfig !== null) {
            $this->router->addCorsConfiguration($path, $corsConfig);
        }
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

            // Überspringe Parameter, die weder im Pfad noch in der Domain vorkommen
            if (!isset($parameterInfo[$paramName]) && !isset($domainParameterInfo[$paramName])) {
                continue;
            }

            // Prüfe auf RouteParam-Attribute
            $routeParamAttrs = $parameter->getAttributes(RouteParam::class);
            if (!empty($routeParamAttrs)) {
                /** @var RouteParam $routeParam */
                $routeParam = $routeParamAttrs[0]->newInstance();

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
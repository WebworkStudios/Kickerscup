<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Scoped;
use App\Infrastructure\Routing\Attributes\Route;
use App\Infrastructure\Routing\Attributes\RouteParam;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\RouteScannerInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
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

            // Wenn die Klasse __invoke hat und Route-Attribute besitzt
            if ($hasInvokeMethod && !empty($classRouteAttributes)) {
                $invokeMethod = $reflector->getMethod('__invoke');

                foreach ($classRouteAttributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();
                    $path = $routeAttribute->path;
                    $methods = $routeAttribute->methods;
                    $name = $routeAttribute->name;

                    // Registriere die Route mit der Klasse als Handler
                    $this->router->addRoute($methods, $path, $className, $name);

                    // Sammle Parameter-Attribute für die URL-Generierung
                    $this->collectParameterAttributes($invokeMethod, $path, $name);
                }
            }

            // Scanne auch Methoden nach Route-Attributen
            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Überspringe Konstruktor und magische Methoden
                if ($method->isConstructor() || strpos($method->getName(), '__') === 0) {
                    continue;
                }

                $methodRouteAttributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

                foreach ($methodRouteAttributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();
                    $path = $routeAttribute->path;
                    $methods = $routeAttribute->methods;
                    $name = $routeAttribute->name;

                    // Registriere die Route mit der Klasse und Methode als Handler
                    $this->router->addRoute($methods, $path, [$className, $method->getName()], $name);

                    // Sammle Parameter-Attribute für die URL-Generierung
                    $this->collectParameterAttributes($method, $path, $name);
                }
            }
        } catch (ReflectionException $e) {
            // Ignoriere Reflection-Fehler und mache mit dem nächsten weiter
        }
    }

    /**
     * Sammelt Parameter-Attribute für eine Methode
     *
     * @param ReflectionMethod $method Die Methode
     * @param string $path Der Pfad der Route
     * @param string|null $name Der Name der Route
     * @return void
     */
    protected function collectParameterAttributes(ReflectionMethod $method, string $path, ?string $name): void
    {
        if ($name === null) {
            return;
        }

        $parameterInfo = [];

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

        // Überprüfe Parameter-Attribute
        foreach ($method->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            // Überspringe Parameter, die nicht im Pfad vorkommen
            if (!isset($parameterInfo[$paramName])) {
                continue;
            }

            // Prüfe auf RouteParam-Attribute
            $routeParamAttrs = $parameter->getAttributes(RouteParam::class);
            if (!empty($routeParamAttrs)) {
                /** @var RouteParam $routeParam */
                $routeParam = $routeParamAttrs[0]->newInstance();

                // Aktualisiere Regex, wenn im Attribut angegeben
                if ($routeParam->regex !== null) {
                    $parameterInfo[$paramName]['regex'] = $routeParam->regex;
                }

                // Markiere als optional, wenn im Attribut angegeben
                $parameterInfo[$paramName]['optional'] = $routeParam->optional;

                // Setze Standardwert, wenn im Attribut angegeben
                if ($routeParam->default !== null) {
                    $parameterInfo[$paramName]['default'] = $routeParam->default;
                }
            }
        }

        // Füge Route-Infos zum URL-Generator hinzu
        $routeInfo = [
            'path' => $path,
            'parameters' => $parameterInfo
        ];

        $this->urlGenerator->addNamedRoute($name, $routeInfo);
    }
}
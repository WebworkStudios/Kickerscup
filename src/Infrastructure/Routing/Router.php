<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
use App\Infrastructure\Routing\Exceptions\MethodNotAllowedException;
use App\Infrastructure\Routing\Exceptions\RouteCreationException;
use App\Infrastructure\Routing\Exceptions\RouteNotFoundException;
use Closure;
use Exception;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Router-Implementierung
 */
#[Injectable]
#[Singleton]
class Router implements RouterInterface
{
    /**
     * Array mit allen registrierten Routen
     *
     * @var array<string, array<string, array>> Strukturiert nach [HTTP-Methode][normalisierter Pfad] => RouteInfo
     */
    protected array $routes = [];

    /**
     * Assoziatives Array mit benannten Routen
     *
     * @var array<string, array> Struktur: [Routenname] => RouteInfo
     */
    protected array $namedRoutes = [];

    /**
     * Konstruktor
     *
     * @param ContainerInterface $container Container für die Auflösung von Controllern
     * @param UrlGeneratorInterface $urlGenerator URL-Generator für benannte Routen
     * @param ResponseFactoryInterface $responseFactory Factory für HTTP-Responses
     */
    public function __construct(
        protected ContainerInterface       $container,
        protected UrlGeneratorInterface    $urlGenerator,
        protected ResponseFactoryInterface $responseFactory
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function addRoute(string|array $methods, string $path, callable|array|string $handler, ?string $name = null): static
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }

        // Normalisiere den Pfad (entferne doppelte Slashes, etc.)
        $path = $this->normalizePath($path);

        // Extrahiere Parameter-Informationen aus dem Pfad
        $parameterInfo = $this->extractParameterInfo($path);
        $pattern = $parameterInfo['pattern'];

        $routeInfo = [
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'parameters' => $parameterInfo['parameters']
        ];

        // Registriere die Route für jede HTTP-Methode
        foreach ($methods as $method) {
            $method = strtoupper($method);
            $this->routes[$method][$path] = $routeInfo;
        }

        // Wenn ein Name angegeben wurde, registriere die Route auch unter diesem Namen
        if ($name !== null) {
            $this->namedRoutes[$name] = $routeInfo;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function match(RequestInterface $request): mixed
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPath());

        // Prüfe, ob die HTTP-Methode überhaupt registrierte Routen hat
        if (!isset($this->routes[$method])) {
            // Prüfe, ob der Pfad für andere Methoden existiert
            $allowedMethods = [];
            foreach (array_keys($this->routes) as $allowedMethod) {
                foreach ($this->routes[$allowedMethod] as $routePath => $routeInfo) {
                    if (preg_match($routeInfo['pattern'], $path, $matches)) {
                        $allowedMethods[] = $allowedMethod;
                        break;
                    }
                }
            }

            if (!empty($allowedMethods)) {
                $exception = new MethodNotAllowedException(
                    "Methode {$method} ist nicht erlaubt für Pfad {$path}."
                );
                $exception->setAllowedMethods($allowedMethods);
                throw $exception;
            }

            return false;
        }

        // Suche nach einer passenden Route für die Methode und den Pfad
        foreach ($this->routes[$method] as $routeInfo) {
            if (preg_match($routeInfo['pattern'], $path, $matches)) {
                // Extrahiere die Parameter-Werte aus den Matches
                $parameters = $this->extractParameterValues($matches, $routeInfo['parameters']);

                return [
                    'route' => $routeInfo,
                    'parameters' => $parameters
                ];
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        $match = $this->match($request);

        if ($match === false) {
            throw new RouteNotFoundException(
                "Keine Route gefunden für {$request->getMethod()} {$request->getPath()}."
            );
        }

        $route = $match['route'];
        $parameters = $match['parameters'];
        $handler = $route['handler'];

        // Rufe den Handler mit den Parametern auf
        $response = $this->callHandler($handler, $parameters, $request);

        // Wenn der Handler bereits eine Response zurückgibt, verwende diese
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // Sonst erstelle eine Response basierend auf dem Rückgabewert
        if (is_string($response)) {
            return $this->responseFactory->createHtml($response);
        }

        if (is_array($response) || is_object($response)) {
            return $this->responseFactory->createJson($response);
        }

        // Fallback für andere Rückgabetypen
        return $this->responseFactory->create(200, (string)$response);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUrl(string $name, array $parameters = []): string
    {
        return $this->urlGenerator->generate($name, $parameters);
    }

    /**
     * Normalisiert einen Pfad
     *
     * @param string $path Der zu normalisierende Pfad
     * @return string Der normalisierte Pfad
     */
    protected function normalizePath(string $path): string
    {
        // Entferne doppelte Slashes und führende/nachfolgende Slashes
        $path = preg_replace('#/+#', '/', trim($path, '/'));

        // Stelle sicher, dass der Pfad mit einem führenden Slash beginnt
        if ($path !== '') {
            $path = '/' . $path;
        } else {
            $path = '/';
        }

        return $path;
    }

    /**
     * Extrahiert Parameter-Informationen aus einem Pfad
     *
     * @param string $path Der Pfad mit Parametern
     * @return array Ein Array mit Pattern und Parameter-Informationen
     */
    protected function extractParameterInfo(string $path): array
    {
        $parameters = [];
        $pattern = $path;

        // Parameter im Format {name} oder {name:regex} erkennen
        if (preg_match_all('#{([^:}]+)(?::([^}]+))?}#', $path, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                $regex = $match[2] ?? '[^/]+';

                // Speichere Parameter-Informationen
                $parameters[$name] = [
                    'name' => $name,
                    'regex' => $regex
                ];

                // Ersetze Parameter im Pattern mit regulärem Ausdruck
                $pattern = str_replace(
                    $match[0],
                    '(?<' . $name . '>' . $regex . ')',
                    $pattern
                );
            }
        }

        // Konvertiere den Pfad in ein reguläres Ausdrucksmuster
        $pattern = '#^' . $pattern . '$#';

        return [
            'pattern' => $pattern,
            'parameters' => $parameters
        ];
    }

    /**
     * Extrahiert Parameter-Werte aus Matches
     *
     * @param array $matches Matches aus dem regulären Ausdruck
     * @param array $parameterInfo Parameter-Informationen
     * @return array Extrahierte Parameter-Werte
     */
    protected function extractParameterValues(array $matches, array $parameterInfo): array
    {
        $parameters = [];

        foreach ($parameterInfo as $name => $info) {
            if (isset($matches[$name])) {
                $parameters[$name] = $matches[$name];
            }
        }

        return $parameters;
    }

    /**
     * Ruft den Handler mit den Parametern auf
     *
     * @param callable|array|string $handler Der Handler
     * @param array $parameters Die Parameter aus der URL
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Das Ergebnis des Handler-Aufrufs
     */
    protected function callHandler(callable|array|string $handler, array $parameters, RequestInterface $request): mixed
    {
        // Wenn der Handler ein Closure ist
        if ($handler instanceof Closure) {
            return $this->callClosure($handler, $parameters, $request);
        }

        // Wenn der Handler ein Array [Controller, Methode] ist
        if (is_array($handler) && count($handler) === 2) {
            return $this->callControllerMethod($handler[0], $handler[1], $parameters, $request);
        }

        // Wenn der Handler ein String ist (Klasse mit __invoke)
        if (is_string($handler)) {
            return $this->callInvokable($handler, $parameters, $request);
        }

        throw new RouteCreationException("Ungültiger Handler-Typ");
    }

    /**
     * Ruft ein Closure mit den Parametern auf
     *
     * @param Closure $closure Das aufzurufende Closure
     * @param array $parameters Die Parameter aus der URL
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Das Ergebnis des Closure-Aufrufs
     */
    protected function callClosure(Closure $closure, array $parameters, RequestInterface $request): mixed
    {
        $reflection = new ReflectionFunction($closure);
        $args = $this->resolveParameters($reflection->getParameters(), $parameters, $request);

        return $reflection->invokeArgs($args);
    }

    /**
     * Ruft eine Controller-Methode mit den Parametern auf
     *
     * @param string|object $controller Der Controller (Name oder Instanz)
     * @param string $method Die aufzurufende Methode
     * @param array $parameters Die Parameter aus der URL
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Das Ergebnis des Methoden-Aufrufs
     */
    protected function callControllerMethod(string|object $controller, string $method, array $parameters, RequestInterface $request): mixed
    {
        // Wenn der Controller ein String ist, instanziiere ihn über den Container
        if (is_string($controller)) {
            $controller = $this->container->get($controller);
        }

        $reflection = new ReflectionMethod($controller, $method);
        $args = $this->resolveParameters($reflection->getParameters(), $parameters, $request);

        return $reflection->invokeArgs($controller, $args);
    }

    /**
     * Ruft eine __invoke-Methode mit den Parametern auf
     *
     * @param string $className Der Name der Klasse
     * @param array $parameters Die Parameter aus der URL
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Das Ergebnis des __invoke-Aufrufs
     */
    protected function callInvokable(string $className, array $parameters, RequestInterface $request): mixed
    {
        $instance = $this->container->get($className);
        $reflection = new ReflectionMethod($instance, '__invoke');
        $args = $this->resolveParameters($reflection->getParameters(), $parameters, $request);

        return $reflection->invokeArgs($instance, $args);
    }

    /**
     * Löst Parameter für einen Methodenaufruf auf
     *
     * @param ReflectionParameter[] $methodParameters Die Parameter der Methode
     * @param array $routeParameters Die Parameter aus der Route
     * @param RequestInterface $request Der HTTP-Request
     * @return array Die aufgelösten Parameter
     */
    protected function resolveParameters(array $methodParameters, array $routeParameters, RequestInterface $request): array
    {
        $resolvedParameters = [];

        foreach ($methodParameters as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();

            // Wenn der Parameter den Typ RequestInterface hat, injiziere den Request
            if ($paramType !== null && !$paramType->isBuiltin() &&
                is_a($paramType->getName(), RequestInterface::class, true)) {
                $resolvedParameters[] = $request;
                continue;
            }

            // Wenn der Parameter in den Routen-Parametern existiert
            if (isset($routeParameters[$paramName])) {
                $value = $routeParameters[$paramName];

                // Typkonvertierung wenn nötig und möglich
                if ($paramType !== null && $paramType->isBuiltin()) {
                    $typeName = $paramType->getName();
                    if ($typeName === 'int' && is_numeric($value)) {
                        $value = (int)$value;
                    } elseif ($typeName === 'float' && is_numeric($value)) {
                        $value = (float)$value;
                    } elseif ($typeName === 'bool') {
                        $value = (bool)$value;
                    }
                }

                $resolvedParameters[] = $value;
                continue;
            }

            // Wenn der Parameter einen Standardwert hat
            if ($param->isDefaultValueAvailable()) {
                $resolvedParameters[] = $param->getDefaultValue();
                continue;
            }

            // Versuche, den Parameter über den Container aufzulösen
            if ($paramType !== null && !$paramType->isBuiltin()) {
                try {
                    $resolvedParameters[] = $this->container->get($paramType->getName());
                    continue;
                } catch (Exception $e) {
                    // Ignoriere Fehler und versuche weitere Auflösungsmethoden
                }
            }

            // Wenn der Parameter null erlaubt
            if ($paramType !== null && $paramType->allowsNull()) {
                $resolvedParameters[] = null;
                continue;
            }

            // Wenn wir hier ankommen, konnte der Parameter nicht aufgelöst werden
            throw new RouteCreationException(
                "Parameter {$paramName} konnte nicht aufgelöst werden."
            );
        }

        return $resolvedParameters;
    }
}
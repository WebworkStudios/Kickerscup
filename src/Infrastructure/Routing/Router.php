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
     * CORS-Konfigurationen für Routen
     *
     * @var array<string, array>
     */
    protected array $corsConfigurations = [];

    /**
     * Redirect-Einträge
     * Struktur: [Quellpfad => [Zielpfad, Statuscode, preserveQueryString]]
     * @var array<string, array>
     */
    protected array $redirects = [];

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
    public function addRoute(string|array $methods, string $path, callable|array|string $handler, ?string $name = null, ?string $domain = null): static
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }

        // Normalisiere den Pfad (entferne doppelte Slashes, etc.)
        $path = $this->normalizePath($path);

        // Normalisiere die Domain (default: null für jede Domain)
        $domain = $domain !== null ? strtolower($domain) : null;

        // Extrahiere Parameter-Informationen aus dem Pfad
        $parameterInfo = $this->extractParameterInfo($path);
        $pattern = $parameterInfo['pattern'];

        $routeInfo = [
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'parameters' => $parameterInfo['parameters'],
            'domain' => $domain
        ];

        // Registriere die Route für jede HTTP-Methode
        foreach ($methods as $method) {
            $method = strtoupper($method);

            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }

            if (!isset($this->routes[$method][$domain])) {
                $this->routes[$method][$domain] = [];
            }

            $this->routes[$method][$domain][$path] = $routeInfo;
        }

        // Wenn ein Name angegeben wurde, registriere die Route auch unter diesem Namen
        if ($name !== null) {
            $this->namedRoutes[$name] = $routeInfo;
        }

        return $this;
    }

    /**
     * Fügt eine Umleitung hinzu
     *
     * @param string $fromPath Quellpfad
     * @param string $toPath Zielpfad (kann auch eine benannte Route sein mit 'name:routeName')
     * @param int $statusCode HTTP-Statuscode (301 = permanent, 302 = temporär)
     * @param bool $preserveQueryString Ob der Query-String übernommen werden soll
     * @return static
     */
    public function addRedirect(
        string $fromPath,
        string $toPath,
        int    $statusCode = 302,
        bool   $preserveQueryString = true
    ): static
    {
        // Normalisiere Quellpfad
        $fromPath = $this->normalizePath($fromPath);

        // Speichere die Umleitung
        $this->redirects[$fromPath] = [
            'toPath' => $toPath,
            'statusCode' => $statusCode,
            'preserveQueryString' => $preserveQueryString
        ];

        return $this;
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
     * {@inheritdoc}
     */
    public function match(RequestInterface $request): mixed
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPath());
        $host = $request->getHost();

        // Prüfe, ob die HTTP-Methode überhaupt registrierte Routen hat
        if (!isset($this->routes[$method])) {
            // Prüfe, ob der Pfad für andere Methoden existiert
            $allowedMethods = [];

            foreach (array_keys($this->routes) as $routeMethod) {
                // Prüfe Domain-spezifische und allgemeine Routen
                $domainMatched = false;

                // Exakte Domain-Prüfung
                if ($host !== null && isset($this->routes[$routeMethod][$host])) {
                    if ($this->matchPath($path, $this->routes[$routeMethod][$host]) !== false) {
                        $allowedMethods[] = $routeMethod;
                        $domainMatched = true;
                    }
                }

                // Dynamische Domain-Prüfung
                if ($host !== null && !$domainMatched) {
                    foreach ($this->routes[$routeMethod] as $domainPattern => $domainRoutes) {
                        if ($domainPattern === null || $domainPattern === $host) {
                            continue;
                        }

                        if (strpos($domainPattern, '{') !== false) {
                            $domainRegex = $this->createDomainRegex($domainPattern);
                            if (preg_match($domainRegex, $host) && $this->matchPath($path, $domainRoutes) !== false) {
                                $allowedMethods[] = $routeMethod;
                                $domainMatched = true;
                                break;
                            }
                        }
                    }
                }

                // Allgemeine Routen-Prüfung (null domain)
                if (!$domainMatched && isset($this->routes[$routeMethod][null])) {
                    if ($this->matchPath($path, $this->routes[$routeMethod][null]) !== false) {
                        $allowedMethods[] = $routeMethod;
                    }
                }
            }

            if (!empty($allowedMethods)) {
                $exception = new MethodNotAllowedException(
                    "Methode {$method} ist nicht erlaubt für Pfad {$path}. Erlaubte Methoden: " . implode(', ', $allowedMethods)
                );
                $exception->setAllowedMethods($allowedMethods);
                throw $exception;
            }

            return false;
        }

        // Strategie für Domain-Matching mit Prioritäten:
        // 1. Exakte Domain-Übereinstimmung
        // 2. Dynamische Domain mit Parametern
        // 3. Domain-unabhängige Routen (null domain)

        // 1. Exakte Domain-Übereinstimmung
        if ($host !== null && isset($this->routes[$method][$host])) {
            $match = $this->matchPath($path, $this->routes[$method][$host]);
            if ($match !== false) {
                return $match;
            }
        }

        // 2. Dynamische Domain-Übereinstimmung
        if ($host !== null) {
            // Sortiere Domain-Patterns nach Spezifität
            $domainPatterns = array_filter(
                array_keys($this->routes[$method] ?? []),
                fn($pattern) => $pattern !== null && $pattern !== $host && strpos($pattern, '{') !== false
            );

            // Priorisiere Patterns mit weniger Parametern (spezifischere zuerst)
            usort($domainPatterns, function ($a, $b) {
                $aCount = substr_count($a, '{');
                $bCount = substr_count($b, '{');
                return $aCount <=> $bCount;
            });

            foreach ($domainPatterns as $domainPattern) {
                $domainRegex = $this->createDomainRegex($domainPattern);
                if (preg_match($domainRegex, $host, $domainMatches)) {
                    $match = $this->matchPath($path, $this->routes[$method][$domainPattern]);
                    if ($match !== false) {
                        // Extrahiere benannte Parameter aus den Domain-Matches
                        foreach ($domainMatches as $key => $value) {
                            if (is_string($key)) {
                                $match['parameters'][$key] = $value;
                            }
                        }

                        // Füge die Domain-Informationen zum Match hinzu
                        $match['domain'] = [
                            'pattern' => $domainPattern,
                            'matched' => $host
                        ];

                        return $match;
                    }
                }
            }
        }

        // 3. Domain-unabhängige Routen (null domain)
        if (isset($this->routes[$method][null])) {
            $match = $this->matchPath($path, $this->routes[$method][null]);
            if ($match !== false) {
                return $match;
            }
        }

        return false;
    }

    /**
     * Prüft, ob ein Pfad mit einer der Routen übereinstimmt
     *
     * @param string $path Der zu prüfende Pfad
     * @param array $routes Die Routen zum Vergleichen
     * @return array|false Die gefundene Route oder false
     */
    protected function matchPath(string $path, array $routes): array|false
    {
        foreach ($routes as $routeInfo) {
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
     * Erstellt ein reguläres Ausdrucksmuster für eine Domain-Pattern
     *
     * @param string $domainPattern Das Domain-Pattern
     * @return string Das reguläre Ausdrucksmuster
     */
    protected function createDomainRegex(string $domainPattern): string
    {
        // Konvertiere das Pattern in einen regulären Ausdruck
        $pattern = preg_replace_callback(
            '#{([^:}]+)(?::([^}]+))?}#',
            function ($matches) {
                $name = $matches[1];
                $regex = $matches[2] ?? '[^.]+';
                return '(?<' . $name . '>' . $regex . ')';
            },
            $domainPattern
        );

        // Escape Punkten in der Domain und füge Anker hinzu
        $pattern = str_replace('.', '\.', $pattern);
        return '#^' . $pattern . '$#';
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        // Prüfe auf Redirects für diesen Pfad
        $redirectResponse = $this->checkForRedirect($request);
        if ($redirectResponse !== null) {
            return $redirectResponse;
        }

        // CORS Preflight-Anfragen behandeln
        if ($request->getMethod() === 'OPTIONS' && $request->hasHeader('access-control-request-method')) {
            return $this->handleCorsPreflightRequest($request);
        }

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
            // CORS-Header für normale Anfragen hinzufügen
            $this->addCorsHeadersToResponse($response, $request);
            return $response;
        }

        // Sonst erstelle eine Response basierend auf dem Rückgabewert
        if (is_string($response)) {
            $response = $this->responseFactory->createHtml($response);
        } elseif (is_array($response) || is_object($response)) {
            $response = $this->responseFactory->createJson($response);
        } else {
            // Fallback für andere Rückgabetypen
            $response = $this->responseFactory->create(200, (string)$response);
        }

        // CORS-Header hinzufügen
        $this->addCorsHeadersToResponse($response, $request);

        return $response;
    }

    /**
     * Prüft, ob für den Request eine Umleitung definiert ist
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return ResponseInterface|null Die Redirect-Response oder null, wenn keine Umleitung definiert ist
     */
    protected function checkForRedirect(RequestInterface $request): ?ResponseInterface
    {
        $path = $this->normalizePath($request->getPath());

        // Prüfe direkte Pfadübereinstimmung
        if (isset($this->redirects[$path])) {
            $redirect = $this->redirects[$path];
            return $this->createRedirectResponse($request, $redirect);
        }

        // Prüfe Wildcard-Umleitungen (z.B. /old/* -> /new/*)
        foreach ($this->redirects as $fromPath => $redirect) {
            if (str_ends_with($fromPath, '/*')) {
                $prefix = substr($fromPath, 0, -2);

                if (str_starts_with($path, $prefix)) {
                    // Wildcard-Umleitung
                    $suffix = substr($path, strlen($prefix));
                    $toPath = $redirect['toPath'];

                    // Falls Zielpfad auch mit Wildcard endet, Suffix hinzufügen
                    if (str_ends_with($toPath, '/*')) {
                        $toPath = substr($toPath, 0, -2) . $suffix;
                    }

                    // Umleitung mit angepasstem Zielpfad erstellen
                    $wildcardRedirect = $redirect;
                    $wildcardRedirect['toPath'] = $toPath;
                    return $this->createRedirectResponse($request, $wildcardRedirect);
                }
            }
        }

        return null;
    }

    /**
     * Erstellt eine Redirect-Response
     *
     * @param RequestInterface $request Der HTTP-Request
     * @param array $redirect Die Redirect-Konfiguration
     * @return ResponseInterface Die Response
     */
    protected function createRedirectResponse(RequestInterface $request, array $redirect): ResponseInterface
    {
        $toPath = $redirect['toPath'];

        // Prüfe, ob der Zielpfad eine benannte Route ist
        if (str_starts_with($toPath, 'name:')) {
            $routeName = substr($toPath, 5);
            $toPath = $this->generateUrl($routeName);
        }

        // Query-String hinzufügen, wenn gewünscht
        if ($redirect['preserveQueryString']) {
            $queryString = $request->getQueryString();
            if ($queryString) {
                $hasQueryString = str_contains($toPath, '?');
                $toPath .= ($hasQueryString ? '&' : '?') . $queryString;
            }
        }

        // Erstelle die Redirect-Response
        return $this->responseFactory->createRedirect($toPath, $redirect['statusCode']);
    }

    /**
     * Behandelt CORS Preflight-Anfragen
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return ResponseInterface Die Response
     */
    protected function handleCorsPreflightRequest(RequestInterface $request): ResponseInterface
    {
        $path = $this->normalizePath($request->getPath());
        $corsConfig = $this->findCorsConfigurationForPath($path);

        // Wenn keine CORS-Konfiguration gefunden wurde, erstelle eine leere Response
        if ($corsConfig === null) {
            return $this->responseFactory->create(204);
        }

        $response = $this->responseFactory->create(204);

        // Origin-Header setzen
        $requestOrigin = $request->getHeader('origin');
        if ($requestOrigin !== null) {
            if ($corsConfig['allowOrigin'] === '*') {
                $response->setHeader('Access-Control-Allow-Origin', '*');
            } elseif (is_array($corsConfig['allowOrigin']) && in_array($requestOrigin, $corsConfig['allowOrigin'])) {
                $response->setHeader('Access-Control-Allow-Origin', $requestOrigin);
                $response->setHeader('Vary', 'Origin');
            } elseif (is_string($corsConfig['allowOrigin']) && $corsConfig['allowOrigin'] === $requestOrigin) {
                $response->setHeader('Access-Control-Allow-Origin', $requestOrigin);
                $response->setHeader('Vary', 'Origin');
            }
        }

        // Methoden-Header setzen
        if ($corsConfig['allowMethods'] === '*') {
            $requestMethod = $request->getHeader('access-control-request-method');
            if ($requestMethod !== null) {
                $response->setHeader('Access-Control-Allow-Methods', $requestMethod);
            }
        } else {
            $allowedMethods = is_array($corsConfig['allowMethods'])
                ? implode(', ', $corsConfig['allowMethods'])
                : $corsConfig['allowMethods'];
            $response->setHeader('Access-Control-Allow-Methods', $allowedMethods);
        }

        // Header-Header setzen
        if ($corsConfig['allowHeaders'] === '*') {
            $requestHeaders = $request->getHeader('access-control-request-headers');
            if ($requestHeaders !== null) {
                $response->setHeader('Access-Control-Allow-Headers', $requestHeaders);
            }
        } else {
            $allowedHeaders = is_array($corsConfig['allowHeaders'])
                ? implode(', ', $corsConfig['allowHeaders'])
                : $corsConfig['allowHeaders'];
            $response->setHeader('Access-Control-Allow-Headers', $allowedHeaders);
        }

        // Credentials-Header setzen
        if ($corsConfig['allowCredentials']) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Max-Age-Header setzen
        $response->setHeader('Access-Control-Max-Age', (string)$corsConfig['maxAge']);

        return $response;
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
     * Fügt CORS-Header zu einer Response hinzu
     *
     * @param ResponseInterface $response Die Response
     * @param RequestInterface $request Der HTTP-Request
     * @return void
     */
    protected function addCorsHeadersToResponse(ResponseInterface $response, RequestInterface $request): void
    {
        $path = $this->normalizePath($request->getPath());
        $corsConfig = $this->findCorsConfigurationForPath($path);

        if ($corsConfig === null) {
            return;
        }

        // Origin-Header setzen
        $requestOrigin = $request->getHeader('origin');
        if ($requestOrigin !== null) {
            if ($corsConfig['allowOrigin'] === '*') {
                $response->setHeader('Access-Control-Allow-Origin', '*');
            } elseif (is_array($corsConfig['allowOrigin']) && in_array($requestOrigin, $corsConfig['allowOrigin'])) {
                $response->setHeader('Access-Control-Allow-Origin', $requestOrigin);
                $response->setHeader('Vary', 'Origin');
            } elseif (is_string($corsConfig['allowOrigin']) && $corsConfig['allowOrigin'] === $requestOrigin) {
                $response->setHeader('Access-Control-Allow-Origin', $requestOrigin);
                $response->setHeader('Vary', 'Origin');
            }
        }

        // Credentials-Header setzen
        if ($corsConfig['allowCredentials']) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Expose-Headers-Header setzen
        if (!empty($corsConfig['exposeHeaders'])) {
            $exposeHeaders = is_array($corsConfig['exposeHeaders'])
                ? implode(', ', $corsConfig['exposeHeaders'])
                : $corsConfig['exposeHeaders'];
            $response->setHeader('Access-Control-Expose-Headers', $exposeHeaders);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateUrl(string $name, array $parameters = []): string
    {
        return $this->urlGenerator->generate($name, $parameters);
    }

    /**
     * Fügt eine CORS-Konfiguration für eine Route hinzu
     *
     * @param string $path Der Pfad der Route
     * @param Cors $corsConfig Die CORS-Konfiguration
     * @return void
     */
    public function addCorsConfiguration(string $path, Cors $corsConfig): void
    {
        $this->corsConfigurations[$path] = [
            'allowOrigin' => $corsConfig->allowOrigin,
            'allowMethods' => $corsConfig->allowMethods,
            'allowHeaders' => $corsConfig->allowHeaders,
            'allowCredentials' => $corsConfig->allowCredentials,
            'maxAge' => $corsConfig->maxAge,
            'exposeHeaders' => $corsConfig->exposeHeaders
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function findCorsConfigurationForPath(string $path): ?array
    {
        // Exakte Übereinstimmung
        if (isset($this->corsConfigurations[$path])) {
            return $this->corsConfigurations[$path];
        }

        // Nearest match (z.B. /api/* für /api/users)
        foreach ($this->corsConfigurations as $routePath => $config) {
            // Pfad endet mit * (Wildcard)
            if (str_ends_with($routePath, '*')) {
                $prefix = rtrim(substr($routePath, 0, -1), '/');
                if (str_starts_with($path, $prefix)) {
                    return $config;
                }
            }
        }

        return null;
    }
}
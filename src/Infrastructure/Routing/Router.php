<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Routing\Attributes\Cors;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
use App\Infrastructure\Routing\Exceptions\MethodNotAllowedException;
use App\Infrastructure\Routing\Exceptions\RouteCreationException;
use App\Infrastructure\Routing\Exceptions\RouteNotFoundException;
use App\Infrastructure\Security\Csrf\Attributes\CsrfProtection;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use Closure;
use Exception;
use ReflectionException;
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
     * Registrierte Error-Handler
     *
     * @var array<int, callable|array|string>
     */
    protected array $errorHandlers = [];

    /**
     * CSRF-Konfigurationen für Routen
     *
     * @var array<string, CsrfProtection>
     */
    protected array $csrfConfigurations = [];


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
        protected ResponseFactoryInterface $responseFactory,
        protected ?LoggerInterface         $logger = null
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

        // Parameter im Format {name} oder {name: regex} erkennen
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
     * Fügt eine Umleitung hinzu
     *
     * @param string $fromPath Quellpfad
     * @param string $toPath Zielpfad (kann auch eine benannte Route sein mit 'name: routeName')
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
     * {@inheritdoc}
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     * @throws RouteCreationException
     * @throws ReflectionException
     */
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        try {
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
                $this->logger->info('Route not found', [
                    'method' => $request->getMethod(),
                    'path' => $request->getPath()
                ]);
                throw new RouteNotFoundException(
                    "Keine Route gefunden für {$request->getMethod()} {$request->getPath()}."
                );
            }

            $route = $match['route'];
            $parameters = $match['parameters'];
            $handler = $route['handler'];
            $path = $request->getPath();

            // CSRF-Validierung durchführen, wenn nötig
            $csrfConfig = $this->findCsrfConfigurationForPath($path);
            if ($csrfConfig !== null && $csrfConfig->enabled && $this->shouldValidateCsrf($request)) {
                $csrfService = $this->container->get(CsrfProtectionInterface::class);
                $token = $this->getTokenFromRequest($request);

                if (!$token || !$csrfService->validateToken($token, $csrfConfig->tokenKey)) {
                    return $this->handleCsrfError();
                }

                if ($csrfConfig->validateOrigin && !$csrfService->validateOrigin([])) {
                    return $this->handleCsrfError("Ungültiger Request-Origin");
                }
            }

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
        } catch (RouteNotFoundException $e) {
            return $this->handleNotFoundError($request, $e);
        } catch (MethodNotAllowedException $e) {
            return $this->handleMethodNotAllowedError($request, $e);
        }
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
     * {@inheritdoc}
     */
    public function generateUrl(string $name, array $parameters = []): string
    {
        return $this->urlGenerator->generate($name, $parameters);
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
        $this->setCorsHeaders($request, $response, $corsConfig);

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
                $prefix = rtrim(string: substr($routePath, 0, -1), characters: '/');
                if (str_starts_with($path, $prefix)) {
                    return $config;
                }
            }
        }

        return null;
    }

    protected function setCorsHeaders(RequestInterface $request, ResponseInterface $response, array $corsConfig): void
    {
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
    }

    /**
     * {@inheritdoc}
     * @throws MethodNotAllowedException
     */
    public function match(RequestInterface $request): array|false
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

                        if (str_contains($domainPattern, '{')) {
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
                    "Methode $method ist nicht erlaubt für Pfad $path. Erlaubte Methoden: " . implode(', ', $allowedMethods)
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
                fn($pattern) => $pattern !== null && $pattern !== $host && str_contains($pattern, '{') !== false
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
     * @return array{route: array, parameters: array}|false Die gefundene Route oder false
     */
    protected function matchPath(string $path, array $routes): array|false
    {
        // Finde eine passende Route mit array_find
        $match = array_find($routes, function ($routeInfo) use ($path) {
            return preg_match($routeInfo['pattern'], $path);
        });

        if ($match !== null) {
            $matches = [];
            preg_match($match['pattern'], $path, $matches);
            $parameters = $this->extractParameterValues($matches, $match['parameters']);

            return [
                'route' => $match,
                'parameters' => $parameters
            ];
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
        // Nutze array_filter für eine effizientere Implementation
        return array_filter(
            $matches,
            fn($key) => is_string($key) && isset($parameterInfo[$key]),
            ARRAY_FILTER_USE_KEY
        );
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
     * Findet die CSRF-Konfiguration für einen Pfad
     *
     * @param string $path Der Pfad
     * @return CsrfProtection|null Die CSRF-Konfiguration oder null
     */
    public function findCsrfConfigurationForPath(string $path): ?CsrfProtection
    {
        // Exakte Übereinstimmung
        if (isset($this->csrfConfigurations[$path])) {
            return $this->csrfConfigurations[$path];
        }

        // Nearest match (z.B. /admin/* für /admin/users)
        foreach ($this->csrfConfigurations as $routePath => $config) {
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

    /**
     * @param RequestInterface $request
     * @return bool
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function shouldValidateCsrf(RequestInterface $request): bool
    {
        $csrfService = $this->container->get(CsrfProtectionInterface::class);
        return $csrfService->shouldProtectRequest($request);
    }

    /**
     * Extrahiert das CSRF-Token aus dem Request
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        // Token aus POST/PUT-Parameter
        $token = $request->getInput('_csrf_token');
        if ($token) {
            return $token;
        }

        // Token aus Header (für AJAX-Requests)
        $token = $request->getHeader('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        // Token aus X-XSRF-TOKEN Header (für AJAX mit Cookies)
        $token = $request->getHeader('X-XSRF-TOKEN');
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Behandelt CSRF-Fehler
     * @param string|null $detailMessage
     * @return ResponseInterface
     */
    protected function handleCsrfError(?string $detailMessage = null): ResponseInterface
    {
        return $this->responseFactory->createForbidden("CSRF-Fehler: " . ($detailMessage ?? "Token ungültig"));
    }

    /**
     * Ruft den Handler mit den Parametern auf
     *
     * @param callable|array|string $handler Der Handler
     * @param array $parameters Die Parameter aus der URL
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Das Ergebnis des Handler-Aufrufs
     * @throws ContainerException
     * @throws NotFoundException
     * @throws RouteCreationException
     * @throws ReflectionException
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
     * @throws RouteCreationException
     * @throws ReflectionException
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
     * @throws RouteCreationException
     */
    protected function resolveParameters(array $methodParameters, array $routeParameters, RequestInterface $request): array
    {
        $resolvedParameters = [];

        foreach ($methodParameters as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();

            // Spezialfall für Parameter namens "request"
            if ($paramName === 'request') {
                $resolvedParameters[] = $request;
                continue;
            }

            // Wenn der Parameter in den Routen-Parametern existiert
            if (isset($routeParameters[$paramName])) {
                $value = $routeParameters[$paramName];

                // Typ konvertierung, wenn nötig und möglich
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
                } catch (Exception) {
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
                "Parameter $paramName konnte nicht aufgelöst werden."
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
     * @throws RouteCreationException
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
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
     * @throws ContainerException
     * @throws NotFoundException
     * @throws RouteCreationException
     * @throws ReflectionException
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
        $this->setCorsHeaders($request, $response, $corsConfig);

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
     * Behandelt 404 Not Found Fehler
     *
     * @param RequestInterface $request Der HTTP-Request
     * @param RouteNotFoundException $exception Die ausgelöste Exception
     * @return ResponseInterface Die Fehler-Response
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws RouteCreationException
     */
    protected function handleNotFoundError(RequestInterface $request, RouteNotFoundException $exception): ResponseInterface
    {
        // Prüfen, ob ein Handler für 404 registriert wurde
        if (isset($this->errorHandlers[404])) {
            return $this->callErrorHandler($this->errorHandlers[404], $request, $exception);
        }

        // Standard 404-Response
        return $this->responseFactory->createNotFound($exception->getMessage());
    }

    /**
     * Ruft einen benutzerdefinierten Error-Handler auf
     *
     * @param callable|array|string $handler Der Error-Handler
     * @param RequestInterface $request Der HTTP-Request
     * @param Exception $exception Die ausgelöste Exception
     * @return ResponseInterface Die Response vom Handler
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws RouteCreationException
     */
    protected function callErrorHandler(callable|array|string $handler, RequestInterface $request, Exception $exception): ResponseInterface
    {
        $response = $this->callHandler($handler, ['exception' => $exception], $request);

        // Wenn der Handler keine Response zurückgibt, erstelle eine Standard-Response
        if (!$response instanceof ResponseInterface) {
            if (is_string($response)) {
                $response = $this->responseFactory->createHtml($response, $exception instanceof MethodNotAllowedException ? 405 : 404);
            } elseif (is_array($response) || is_object($response)) {
                $response = $this->responseFactory->createJson($response, $exception instanceof MethodNotAllowedException ? 405 : 404);
            } else {
                $statusCode = $exception instanceof MethodNotAllowedException ? 405 : 404;
                $response = $this->responseFactory->create($statusCode, (string)$response);
            }
        }

        return $response;
    }

    /**
     * Behandelt 405 Method Not Allowed Fehler
     *
     * @param RequestInterface $request Der HTTP-Request
     * @param MethodNotAllowedException $exception Die ausgelöste Exception
     * @return ResponseInterface Die Fehler-Response
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws RouteCreationException
     */
    protected function handleMethodNotAllowedError(RequestInterface $request, MethodNotAllowedException $exception): ResponseInterface
    {
        // Setze den Allow-Header mit den erlaubten Methoden
        $response = $this->responseFactory->create(405, $exception->getMessage());
        $response->setHeader('Allow', implode(', ', $exception->getAllowedMethods()));

        // Prüfen, ob ein Handler für 405 registriert wurde
        if (isset($this->errorHandlers[405])) {
            $customResponse = $this->callErrorHandler($this->errorHandlers[405], $request, $exception);

            // Stelle sicher, dass der Allow-Header auch in der benutzerdefinierten Response gesetzt ist
            if (!$customResponse->hasHeader('Allow')) {
                $customResponse->setHeader('Allow', implode(', ', $exception->getAllowedMethods()));
            }

            return $customResponse;
        }

        return $response;
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
     * Registriert einen Handler für einen HTTP-Fehlercode
     *
     * @param int $statusCode HTTP-Statuscode
     * @param callable|array|string $handler Handler-Funktion oder Controller
     * @return static
     */
    public function registerErrorHandler(int $statusCode, callable|array|string $handler): static
    {
        $this->errorHandlers[$statusCode] = $handler;
        return $this;
    }

    /**
     * Fügt eine CSRF-Konfiguration für eine Route hinzu
     *
     * @param string $path Der Pfad der Route
     * @param CsrfProtection $csrfConfig Die CSRF-Konfiguration
     * @return void
     */
    public function addCsrfConfiguration(string $path, CsrfProtection $csrfConfig): void
    {
        $this->csrfConfigurations[$path] = $csrfConfig;
    }
}
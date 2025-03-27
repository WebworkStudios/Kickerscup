# Router-Dokumentation

## Überblick

Der Router ist ein leistungsfähiges Routing-System in der PHP-Anwendung, das flexible und moderne Routing-Funktionen bietet. Er unterstützt fortschrittliche Funktionen wie attributbasiertes Routing, Domain-Routing, Parameterextraktion, CORS-Konfiguration, CSRF-Schutz und vieles mehr.

## Beispiel mit Actions

### Benutzer-Action für Listenansicht

```php
namespace App\Presentation\Actions\Users;

use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Cors;
use App\Infrastructure\Security\Csrf\Attributes\CsrfProtection;

#[Get('/users', name: 'user_list')]
#[Cors(
    allowOrigin: ['https://frontend.example.com'],
    allowMethods: ['GET'],
    allowCredentials: true
)]
#[CsrfProtection(enabled: false)] // Optional bei GET-Requests
class ListUsersAction
{
    public function __invoke(UserService $userService): array
    {
        return [
            'users' => $userService->getAllUsers(),
            'total' => $userService->getUserCount()
        ];
    }
}
```

### Benutzer-Erstellungs-Action

```php
namespace App\Presentation\Actions\Users;

use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Routing\Attributes\RouteParam;
use App\Infrastructure\Security\Csrf\Attributes\CsrfProtection;

#[Post('/users', name: 'user_create')]
#[CsrfProtection(enabled: true)]
class CreateUserAction
{
    public function __invoke(
        UserService $userService, 
        #[RouteParam(optional: true, default: false)] 
        bool $sendWelcomeEmail = false
    ): array {
        $userData = $this->request->getJsonBody();
        $user = $userService->createUser($userData, $sendWelcomeEmail);
        
        return [
            'user' => $user,
            'message' => 'Benutzer erfolgreich erstellt'
        ];
    }
}
```

### Benutzer-Profil-Action mit Domain-Routing

```php
namespace App\Presentation\Actions\Users;

use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\RouteParam;

#[Get('/profile', domain: '{subdomain}.example.com')]
class UserProfileAction
{
    public function __invoke(
        UserService $userService, 
        #[RouteParam(regex: '[a-z0-9]+')]
        string $subdomain
    ): array {
        $user = $userService->getUserBySubdomain($subdomain);
        return [
            'profile' => $user->getPublicProfile(),
            'subdomain' => $subdomain
        ];
    }
}
```

## Routing-Funktionen

### 1. Routen-Definition

Routen können über Attribute auf Klassen und Methoden definiert werden:

- `Route`: Basis-Routing-Attribut
- `Get`, `Post`, `Put`, `Patch`, `Delete`: HTTP-Methoden-spezifische Attribute

### 2. Parameter-Behandlung

- Dynamische Routen-Parameter mit optionaler Regex-Validierung
- Automatische Typumwandlung (int, float, bool)
- Optionale Parameter mit Standardwerten

### 3. Domain-Routing

Unterstützt Domain-spezifisches Routing mit Parameterextraktion:
- Subdomains
- Dynamische Domain-Parameter
- Flexible Konfigurationsmöglichkeiten

### 4. CORS-Konfiguration

Konfigurierbare Cross-Origin Resource Sharing:
- Herkunft (Origin) einschränken
- Erlaubte Methoden definieren
- Credentials-Handling

### 5. CSRF-Schutz

Integrierte CSRF-Token-Validierung:
- Aktivierung/Deaktivierung pro Route
- Herkunfts-Validierung
- Sichere Standardeinstellungen

### 6. Weiterleitungen

Einfache Routen-Weiterleitung:
- Permanente und temporäre Umleitungen
- Wildcard-Unterstützung
- Beibehaltung von Query-Parametern

## URL-Generierung

Generierung von URLs für benannte Routen mit Parameterunterstützung:

```php
$url = $urlGenerator->generate('user_profile', ['id' => 123]);
```

## Fehler-Behandlung

Benutzerdefinierte Fehler-Handler für HTTP-Statuscodes:

```php
$router->registerErrorHandler(404, function($request) {
    return new Response('Nicht gefunden', 404);
});
```

## Sicherheitsmerkmale

- CSRF-Schutz
- CORS-Konfiguration
- Domain-basierte Zugriffskontrolle
- Parameter-Validierung
- Sichere URL-Generierung

## Leistungsoptimierung

- Effizienter Routen-Abgleich
- Minimaler Laufzeit-Overhead
- Zwischenspeicherung von Routen-Informationen

## Kompatibilität

- PHP 8.4 kompatibel
- Verwendet moderne PHP-Funktionen wie Attribute, Union-Types
- Unterstützt Dependency Injection über Container

## Best Practices

1. Verwenden Sie Attribute für klares, deklaratives Routing
2. Validieren und bereinigen Sie Routen-Parameter
3. Implementieren Sie CORS und CSRF-Schutz
4. Verwenden Sie benannte Routen für URL-Generierung
5. Behandeln Sie Fehler angemessen

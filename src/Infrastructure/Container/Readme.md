# Dependency Injection Container

## Übersicht

Diese Implementierung bietet einen leistungsfähigen, flexiblen Dependency Injection Container für PHP 8.4 Anwendungen.
Der Container ermöglicht die Verwaltung von Abhängigkeiten, automatische Auflösung von Typen sowie verschiedene
Lebensdauer-Optionen für Services.

## Funktionen

- **Automatische Dependency Injection** via Reflection API
- **Verschiedene Lebenszyklen** für registrierte Services:
    - **Singleton**: Eine einzige Instanz für die gesamte Anwendung
    - **Scoped**: Eine Instanz pro Scope (z.B. pro Request)
    - **Transient**: Eine neue Instanz bei jeder Anfrage
- **Service Provider** zur organisierten Registrierung von Services
- **Automatische Service-Erkennung** mittels Attributen
- **Factory-Unterstützung** für komplexe Service-Erstellung
- **Fehlerbehandlung** mit detaillierten Fehlermeldungen

## Installation

Um den Container in deinem Projekt zu verwenden, kopiere den Code in dein Projekt und stelle sicher, dass PHP 8.4 oder
höher verfügbar ist.

## Grundlegende Verwendung

### Service registrieren

```php
// Container erstellen
$container = new Container();

// Einfache Bindung (Transient)
$container->bind(InterfaceA::class, ImplementationA::class);

// Singleton registrieren
$container->singleton(InterfaceB::class, ImplementationB::class);

// Scoped Service registrieren
$container->scoped(InterfaceC::class, ImplementationC::class);

// Factory registrieren
$container->factory(ComplexService::class, new ComplexServiceFactory());
```

### Service abrufen

```php
// Service abrufen
$serviceA = $container->get(InterfaceA::class);

// Service mit Parameter abrufen
$serviceB = $container->makeWith(ServiceB::class, ['parameter' => 'value']);
```

## Attributbasierte Registrierung

Der Container unterstützt die automatische Registrierung von Services mittels Attributen:

```php
// Als Transient markieren (neue Instanz bei jeder Anfrage)
#[Injectable]
#[Transient]
class TransientService implements ServiceInterface
{
    // ...
}

// Als Singleton markieren
#[Injectable]
#[Singleton]
class SingletonService implements ServiceInterface
{
    // ...
}

// Als Scoped markieren
#[Injectable]
#[Scoped]
class ScopedService implements ServiceInterface
{
    // ...
}

// Mit explizitem Alias registrieren
#[Injectable(alias: ServiceInterface::class)]
class ServiceImplementation implements ServiceInterface
{
    // ...
}
```

### Service-Erkennung aktivieren

```php
$scanner = new ServiceScanner($container);
$scanner->scan(['src/Domain', 'src/Application'], 'App');
```

## Service Provider

Service Provider ermöglichen die organisierte Registrierung von zusammengehörigen Services:

```php
class MyServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LoggerInterface::class, FileLogger::class);
        $container->bind(UserRepository::class, DatabaseUserRepository::class);
        // ...
    }
}

// Service Provider registrieren
$provider = new MyServiceProvider();
$provider->register($container);
```

## Fortgeschrittene Funktionen

### Factory verwenden

```php
class ComplexServiceFactory implements FactoryInterface
{
    public function make(ContainerInterface $container, array $parameters = []): mixed
    {
        $dependency = $container->get(DependencyInterface::class);
        $config = $parameters['config'] ?? [];
        
        return new ComplexService($dependency, $config);
    }
}

// Factory registrieren
$container->factory(ComplexService::class, new ComplexServiceFactory());

// Service mit Parametern erstellen
$service = $container->makeWith(ComplexService::class, ['config' => ['key' => 'value']]);
```

### Closures für komplexe Bindungen

```php
$container->bind(ComplexService::class, function (ContainerInterface $c, array $params) {
    $dependency = $c->get(DependencyInterface::class);
    return new ComplexService($dependency, $params['config'] ?? []);
});
```

## Fehlerbehebung

Der Container wirft verschiedene Exceptions bei Problemen:

- `NotFoundException`: Wenn ein angeforderter Service nicht gefunden wurde
- `BindingResolutionException`: Bei Problemen während der Abhängigkeitsauflösung
- `ContainerException`: Bei allgemeinen Container-Fehlern

```php
try {
    $service = $container->get(ServiceInterface::class);
} catch (NotFoundException $e) {
    // Service nicht gefunden
} catch (BindingResolutionException $e) {
    // Problem bei der Auflösung von Abhängigkeiten
} catch (ContainerException $e) {
    // Allgemeiner Container-Fehler
}
```

## Architektur

Der Container besteht aus folgenden Hauptkomponenten:

- **Container**: Zentrale Implementierung des `ContainerInterface`
- **ReflectionResolver**: Für die Auflösung von Typen mittels Reflection
- **ServiceScanner**: Für die automatische Service-Erkennung
- **ServiceProvider**: Basisklasse für Service Provider
- **Attribute**: `Injectable`, `Singleton`, `Scoped`, `Transient`
- **Exceptions**: Spezialisierte Fehlertypen

## Anforderungen

- PHP 8.4 oder höher
- Die PHP Reflection-Erweiterung

## Besonderheiten von PHP 8.4

Diese Implementierung nutzt die neuen Features von PHP 8.4:

- Verbesserte Type-Hints
- Union-Types
- Property Get/Set Modifier
- Neue Array-Funktionen wie `array_find`, `array_find_key`, `array_any`, `array_all`
- Verbesserte Enum-Unterstützung

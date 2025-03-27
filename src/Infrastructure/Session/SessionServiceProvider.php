<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;
use App\Infrastructure\Session\Store\DefaultSessionStore;
use App\Infrastructure\Session\Store\RedisSessionStore;
use Redis;
use RuntimeException;
use Throwable;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    // src/Infrastructure/Session/SessionServiceProvider.php

    public function register(ContainerInterface $container): void
    {
        // Registriere die Session-Konfiguration
        $container->singleton(SessionConfiguration::class, function () {
            // Hier können wir die Konfiguration aus einer Konfigurations-Datei laden
            // für jetzt verwenden wir die Standard-Konfiguration
            return new SessionConfiguration();
        });

        // Registriere den Session-Store basierend auf der Konfiguration
        $container->bind(SessionStoreInterface::class, function (ContainerInterface $c) {
            $config = $c->get(SessionConfiguration::class);

            // Wenn Redis als Store-Typ konfiguriert ist, prüfe, ob die Erweiterung verfügbar ist
            if ($config->storeType === 'redis') {
                if (extension_loaded('redis')) {
                    return $this->createRedisStore($config);
                } else {
                    // Log eine Warnung, dass wir auf den Default-Store zurückfallen
                    error_log('Redis extension nicht verfügbar. Fallback auf Default-Session-Store.');
                    // Übergebe die Konfiguration an den DefaultSessionStore
                    return new DefaultSessionStore($config);
                }
            }

            // Default-Store verwenden, mit Konfiguration
            return new DefaultSessionStore($config);
        });

        // Registriere die Interfaces und deren Implementierungen
        $container->bind(SessionInterface::class, Session::class);
        $container->bind(FlashMessageInterface::class, FlashMessage::class);

        // Registriere den FlashMessageProvider
        $container->singleton(FlashMessageProvider::class);

        // Registriere die Klassen als Singletons
        $container->singleton(Session::class);
        $container->singleton(FlashMessage::class);
    }

    /**
     * Erstellt einen Redis-Session-Store
     */
    private function createRedisStore(SessionConfiguration $config): RedisSessionStore
    {
        $redisConfig = $config->storeConfig['redis'] ?? [];

        $redis = new Redis();

        try {
            $connected = $redis->connect(
                $redisConfig['host'] ?? '127.0.0.1',
                $redisConfig['port'] ?? 6379,
                $redisConfig['timeout'] ?? 1.0
            );

            if (!$connected) {
                throw new RuntimeException('Verbindung zum Redis-Server fehlgeschlagen');
            }

            if (!empty($redisConfig['auth'])) {
                if (!$redis->auth($redisConfig['auth'])) {
                    throw new RuntimeException('Redis-Authentifizierung fehlgeschlagen');
                }
            }

            if (isset($redisConfig['database'])) {
                if (!$redis->select($redisConfig['database'])) {
                    throw new RuntimeException('Redis-Datenbankauswahl fehlgeschlagen');
                }
            }

            return new RedisSessionStore(
                $redis,
                $config,
                $redisConfig['prefix'] ?? 'sess:'
            );
        } catch (Throwable $e) {
            // Bei Verbindungsproblemen Fehler loggen und Default-Store verwenden
            error_log('Redis-Verbindungsfehler: ' . $e->getMessage());
            throw new RuntimeException('Redis-Verbindung konnte nicht hergestellt werden: ' . $e->getMessage());
        }
    }
}
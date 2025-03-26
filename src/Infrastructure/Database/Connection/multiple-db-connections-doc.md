# Mehrere Datenbankverbindungen verwalten

## Inhaltsverzeichnis

- [Einführung](#einführung)
- [Der ConnectionManager](#der-connectionmanager)
- [Konfiguration von Verbindungen](#konfiguration-von-verbindungen)
- [Verwendung verschiedener Verbindungen](#verwendung-verschiedener-verbindungen)
  - [Standardverbindung festlegen](#standardverbindung-festlegen)
  - [Spezifische Verbindungen verwenden](#spezifische-verbindungen-verwenden)
  - [Verbindung nach Host finden](#verbindung-nach-host-finden)
- [Transaktionen mit mehreren Datenbanken](#transaktionen-mit-mehreren-datenbanken)
- [Verbindungen schließen](#verbindungen-schließen)
- [Beispiele](#beispiele)
  - [Multi-Tenant-Anwendungen](#multi-tenant-anwendungen)
  - [Lese-/Schreibtrennung](#lese-schreibtrennung)
  - [Datenbankübergreifende Operationen](#datenbankübergreifende-operationen)

## Einführung

In komplexen Anwendungen ist es oft erforderlich, mit mehreren Datenbanken zu arbeiten. Dies kann verschiedene Gründe haben:

- **Multi-Tenant-Architektur**: Jeder Kunde hat seine eigene Datenbank
- **Leistungsoptimierung**: Trennung von Lese- und Schreiboperationen auf verschiedene Server
- **Datenbanksharding**: Verteilung der Daten auf mehrere Datenbanken für horizontale Skalierung
- **Legacy-Integration**: Zugriff auf bestehende Systeme mit eigenen Datenbanken

Das Datenbank-Subsystem bietet einen flexiblen Mechanismus, um mehrere Verbindungen zu verwalten und nahtlos zwischen ihnen zu wechseln.

## Der ConnectionManager

Der `ConnectionManager` ist die zentrale Komponente für die Verwaltung mehrerer Datenbankverbindungen. Er bietet folgende Hauptfunktionen:

- Konfiguration und Initialisierung von Datenbankverbindungen
- Bereitstellung von Verbindungen an andere Komponenten
- Verwaltung des Verbindungslebenszyklus
- Definition einer Standardverbindung

```php
// ConnectionManager aus dem DI-Container holen
$connectionManager = $container->get(ConnectionManager::class);

// Oder mit Dependency Injection
public function __construct(
    private readonly ConnectionManager $connectionManager
) {
    // ...
}
```

## Konfiguration von Verbindungen

Jede Datenbankverbindung wird durch ein `ConnectionConfiguration`-Objekt definiert:

```php
// Konfiguration für eine Verbindung erstellen
$config = new ConnectionConfiguration(
    host: 'db1.example.com',
    database: 'app_production',
    username: 'app_user',
    password: 'secret',
    port: 3306,
    charset: 'utf8mb4',
    options: [/* PDO-Optionen */]
);

// Verbindung zum ConnectionManager hinzufügen
$connectionManager->addConnection('production', $config);

// Weitere Verbindung für Lesezugriffe hinzufügen
$readConfig = new ConnectionConfiguration(
    host: 'read-replica.example.com',
    database: 'app_production',
    username: 'readonly_user',
    password: 'readonly_secret'
);
$connectionManager->addConnection('read_replica', $readConfig);
```

## Verwendung verschiedener Verbindungen

### Standardverbindung festlegen

```php
// Legt die Standardverbindung fest, die verwendet wird, wenn keine spezifische Verbindung angegeben ist
$connectionManager->setDefaultConnection('production');

// Die Standardverbindung wird verwendet, wenn keine andere angegeben ist
$defaultConnection = $connectionManager->getDefaultConnection();
```

### Spezifische Verbindungen verwenden

```php
// Eine bestimmte Verbindung abrufen
$connection = $connectionManager->getConnection('read_replica');

// Verbindung für einen QueryBuilder festlegen
$query = (new SelectQueryBuilder($connectionManager))
    ->connection('read_replica')
    ->table('users');

// Alternativ: Verbindungsobjekt direkt übergeben
$readConnection = $connectionManager->getConnection('read_replica');
$query = (new SelectQueryBuilder($connectionManager))
    ->connection($readConnection)
    ->table('users');
```

### Verbindung nach Host finden

```php
// Findet eine Verbindung basierend auf dem Host-Namen
$connection = $connectionManager->findConnectionByHost('db2.example.com');

if ($connection !== null) {
    // Verbindung gefunden und kann verwendet werden
    $query = (new SelectQueryBuilder($connectionManager))
        ->connection($connection)
        ->table('logs');
}
```

## Transaktionen mit mehreren Datenbanken

Bei Verwendung mehrerer Datenbanken müssen Transaktionen für jede Verbindung separat verwaltet werden:

```php
// Verbindungen holen
$primaryDb = $connectionManager->getConnection('primary');
$loggingDb = $connectionManager->getConnection('logging');

// Transaktionen starten
$primaryDb->beginTransaction();
$loggingDb->beginTransaction();

try {
    // Operationen auf beiden Datenbanken durchführen
    $userQuery = (new UpdateQueryBuilder($connectionManager))
        ->connection($primaryDb)
        ->table('users')
        ->set('status', 'active')
        ->where('id', $userId)
        ->execute();
    
    $logQuery = (new InsertQueryBuilder($connectionManager))
        ->connection($loggingDb)
        ->table('activity_logs')
        ->values([
            'user_id' => $userId,
            'action' => 'status_change',
            'created_at' => new DateTime()
        ])
        ->execute();
    
    // Wenn alles erfolgreich war, beide Transaktionen committen
    $primaryDb->commit();
    $loggingDb->commit();
    
} catch (Exception $e) {
    // Bei Fehlern beide Transaktionen zurückrollen
    $primaryDb->rollback();
    $loggingDb->rollback();
    throw $e;
}
```

## Verbindungen schließen

```php
// Eine bestimmte Verbindung schließen
$connectionManager->closeConnection('temporary_db');

// Alle Verbindungen schließen (z.B. am Ende des Request-Lebenszyklus)
$connectionManager->closeConnection();
```

## Beispiele

### Game- und Forum-Datenbanken

Ein typisches Beispiel für die Verwendung mehrerer Datenbanken ist die Kombination einer Spiele-Plattform mit einem zugehörigen Forum. Hier sind die Spieledaten (Benutzer, Spielstände, Inventar) in einer Datenbank gespeichert, während die Forumdaten (Beiträge, Threads, Likes) in einer separaten Datenbank liegen:

```php
// Einrichtung der Verbindungen
public function configureGameAndForumConnections(ConnectionManager $connectionManager): void
{
    // Spiel-Datenbank
    $gameConfig = new ConnectionConfiguration(
        host: 'game-db.example.com',
        database: 'game_production',
        username: 'game_user',
        password: 'game_password'
    );
    $connectionManager->addConnection('game', $gameConfig);
    
    // Forum-Datenbank
    $forumConfig = new ConnectionConfiguration(
        host: 'forum-db.example.com',
        database: 'forum_production',
        username: 'forum_user',
        password: 'forum_password'
    );
    $connectionManager->addConnection('forum', $forumConfig);
    
    // Standardverbindung festlegen (für allgemeine Abfragen)
    $connectionManager->setDefaultConnection('game');
}

// Daten zwischen den Datenbanken verknüpfen
class PlayerProfileService {
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {}
    
    public function getPlayerWithForumActivity(int $playerId): array
    {
        // Spielerdaten aus der Game-Datenbank abrufen
        $player = (new SelectQueryBuilder($this->connectionManager))
            ->connection('game')
            ->table('players')
            ->select(['id', 'username', 'level', 'experience', 'last_login'])
            ->where('id', $playerId)
            ->first();
            
        if ($player === null) {
            throw new PlayerNotFoundException("Spieler mit ID {$playerId} nicht gefunden");
        }
        
        // Forumaktivität des Spielers abrufen
        $forumActivity = (new SelectQueryBuilder($this->connectionManager))
            ->connection('forum')
            ->table('forum_users')
            ->select([
                'forum_users.id as forum_user_id',
                'forum_users.reputation',
                $this->connectionManager->getConnection('forum')->raw('COUNT(posts.id) as post_count'),
                $this->connectionManager->getConnection('forum')->raw('COUNT(threads.id) as thread_count')
            ])
            ->leftJoin('posts', 'forum_users.id = posts.user_id')
            ->leftJoin('threads', 'forum_users.id = threads.created_by')
            ->where('forum_users.game_player_id', $playerId)
            ->groupBy(['forum_users.id', 'forum_users.reputation'])
            ->first() ?? [
                'forum_user_id' => null,
                'reputation' => 0,
                'post_count' => 0,
                'thread_count' => 0
            ];
            
        // Letzte Forumbeiträge des Spielers
        $recentPosts = [];
        if ($forumActivity['forum_user_id'] !== null) {
            $recentPosts = (new SelectQueryBuilder($this->connectionManager))
                ->connection('forum')
                ->table('posts')
                ->select(['id', 'thread_id', 'title', 'created_at'])
                ->where('user_id', $forumActivity['forum_user_id'])
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get();
        }
        
        // Statistiken des Spielers aus der Game-Datenbank
        $gameStats = (new SelectQueryBuilder($this->connectionManager))
            ->connection('game')
            ->table('player_statistics')
            ->select(['total_playtime', 'wins', 'losses', 'achievements_completed'])
            ->where('player_id', $playerId)
            ->first() ?? [
                'total_playtime' => 0,
                'wins' => 0,
                'losses' => 0,
                'achievements_completed' => 0
            ];
            
        // Daten zusammenführen
        return [
            'player' => $player,
            'game_stats' => $gameStats,
            'forum_activity' => [
                'reputation' => $forumActivity['reputation'],
                'post_count' => $forumActivity['post_count'],
                'thread_count' => $forumActivity['thread_count'],
                'recent_posts' => $recentPosts
            ]
        ];
    }
    
    public function syncPlayerActivityToForum(int $playerId): void
    {
        // Transaktion in der Forum-Datenbank starten
        $forumConnection = $this->connectionManager->getConnection('forum');
        $forumConnection->beginTransaction();
        
        try {
            // Spieler in der Game-Datenbank abrufen
            $player = (new SelectQueryBuilder($this->connectionManager))
                ->connection('game')
                ->table('players')
                ->select(['id', 'username', 'level', 'experience', 'achievements_count'])
                ->where('id', $playerId)
                ->first();
                
            if ($player === null) {
                throw new PlayerNotFoundException("Spieler mit ID {$playerId} nicht gefunden");
            }
            
            // Prüfen, ob der Spieler bereits im Forum existiert
            $forumUser = (new SelectQueryBuilder($this->connectionManager))
                ->connection('forum')
                ->table('forum_users')
                ->select(['id'])
                ->where('game_player_id', $playerId)
                ->first();
                
            if ($forumUser === null) {
                // Benutzer im Forum anlegen
                $forumUserId = (new InsertQueryBuilder($this->connectionManager))
                    ->connection('forum')
                    ->table('forum_users')
                    ->values([
                        'game_player_id' => $playerId,
                        'username' => $player['username'],
                        'display_name' => $player['username'],
                        'joined_at' => new DateTime(),
                        'game_level' => $player['level']
                    ])
                    ->executeAndGetId();
                    
                // Forum-Berechtigungen setzen
                (new InsertQueryBuilder($this->connectionManager))
                    ->connection('forum')
                    ->table('forum_user_permissions')
                    ->values([
                        'user_id' => $forumUserId,
                        'can_post' => true,
                        'can_create_threads' => true,
                        'can_upload_attachments' => $player['level'] >= 5
                    ])
                    ->execute();
            } else {
                // Benutzer im Forum aktualisieren
                (new UpdateQueryBuilder($this->connectionManager))
                    ->connection('forum')
                    ->table('forum_users')
                    ->set('display_name', $player['username'])
                    ->set('game_level', $player['level'])
                    ->set('achievements_count', $player['achievements_count'])
                    ->set('last_synced_at', new DateTime())
                    ->where('id', $forumUser['id'])
                    ->execute();
                    
                // Forum-Berechtigungen aktualisieren
                (new UpdateQueryBuilder($this->connectionManager))
                    ->connection('forum')
                    ->table('forum_user_permissions')
                    ->set('can_upload_attachments', $player['level'] >= 5)
                    ->set('can_create_polls', $player['level'] >= 10)
                    ->where('user_id', $forumUser['id'])
                    ->execute();
            }
            
            // Transaktion abschließen
            $forumConnection->commit();
            
        } catch (Exception $e) {
            // Bei Fehlern Transaktion zurückrollen
            $forumConnection->rollback();
            throw $e;
        }
    }
}
```

### Multi-Tenant-Anwendungen

In einer Multi-Tenant-Architektur hat jeder Kunde (Tenant) eine eigene Datenbank:

```php
class TenantDatabaseResolver {
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly TenantRegistry $tenantRegistry
    ) {}
    
    public function resolveTenantConnection(string $tenantId): ConnectionInterface {
        // Prüfen, ob Verbindung bereits existiert
        try {
            return $this->connectionManager->getConnection("tenant_{$tenantId}");
        } catch (ConnectionException $e) {
            // Verbindung existiert noch nicht, also erstellen
            $tenantInfo = $this->tenantRegistry->getTenant($tenantId);
            
            $config = new ConnectionConfiguration(
                host: $tenantInfo->dbHost,
                database: $tenantInfo->dbName,
                username: $tenantInfo->dbUser,
                password: $tenantInfo->dbPassword
            );
            
            $this->connectionManager->addConnection("tenant_{$tenantId}", $config);
            return $this->connectionManager->getConnection("tenant_{$tenantId}");
        }
    }
    
    public function getTenantQuery(string $tenantId): SelectQueryBuilder {
        $connection = $this->resolveTenantConnection($tenantId);
        return (new SelectQueryBuilder($this->connectionManager))
            ->connection($connection);
    }
}

// Verwendung
$tenantQuery = $tenantResolver->getTenantQuery('tenant123');
$users = $tenantQuery->table('users')->get();
```

### Lese-/Schreibtrennung

In Hochlast-Umgebungen ist es üblich, Leseoperationen von Schreiboperationen zu trennen:

```php
class DatabaseRouter {
    private const READ_OPERATIONS = ['select', 'first', 'get', 'count', 'sum', 'avg', 'min', 'max'];
    private const WRITE_OPERATIONS = ['insert', 'update', 'delete'];
    
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {}
    
    public function route(string $operation): ConnectionInterface {
        if (in_array(strtolower($operation), self::READ_OPERATIONS)) {
            // Wähle zufällig eine Leseverbindung aus, für Load Balancing
            $readReplicas = ['read_replica1', 'read_replica2', 'read_replica3'];
            $randomReplica = $readReplicas[array_rand($readReplicas)];
            return $this->connectionManager->getConnection($randomReplica);
        } else {
            // Schreiboperationen gehen immer zum Master
            return $this->connectionManager->getConnection('master');
        }
    }
}

// Verwendung
$router = new DatabaseRouter($connectionManager);

// Für Lesevorgänge
$connection = $router->route('select');
$query = (new SelectQueryBuilder($connectionManager))
    ->connection($connection)
    ->table('products');

// Für Schreibvorgänge
$connection = $router->route('update');
$query = (new UpdateQueryBuilder($connectionManager))
    ->connection($connection)
    ->table('orders');
```

### Datenbankübergreifende Operationen

Manchmal müssen Daten aus verschiedenen Datenbanken kombiniert werden:

```php
class CrossDatabaseService {
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {}
    
    public function getUsersWithOrders(): array {
        // Daten aus der ersten Datenbank holen
        $users = (new SelectQueryBuilder($connectionManager))
            ->connection('users_db')
            ->table('users')
            ->select(['id', 'name', 'email'])
            ->where('status', 'active')
            ->get();
        
        // Wenn keine Benutzer gefunden wurden, leeres Array zurückgeben
        if (empty($users)) {
            return [];
        }
        
        // IDs für die zweite Abfrage extrahieren
        $userIds = array_map(fn($user) => $user['id'], $users);
        
        // Daten aus der zweiten Datenbank holen
        $orders = (new SelectQueryBuilder($connectionManager))
            ->connection('orders_db')
            ->table('orders')
            ->select(['user_id', 'COUNT(*) as order_count', 'SUM(total) as total_spent'])
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get();
        
        // Ergebnisse zusammenführen
        $ordersByUser = [];
        foreach ($orders as $order) {
            $ordersByUser[$order['user_id']] = [
                'order_count' => $order['order_count'],
                'total_spent' => $order['total_spent']
            ];
        }
        
        // Daten kombinieren
        foreach ($users as &$user) {
            $userId = $user['id'];
            $user['orders'] = $ordersByUser[$userId] ?? [
                'order_count' => 0,
                'total_spent' => 0
            ];
        }
        
        return $users;
    }
}
```

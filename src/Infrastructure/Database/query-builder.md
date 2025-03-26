# QueryBuilder Dokumentation

## Inhaltsverzeichnis

- [Einführung](#einführung)
- [Grundlegende Verwendung](#grundlegende-verwendung)
- [Select Abfragen](#select-abfragen)
  - [Spalten auswählen](#spalten-auswählen)
  - [Where-Bedingungen](#where-bedingungen)
  - [Joins](#joins)
  - [Gruppierung und Sortierung](#gruppierung-und-sortierung)
  - [Limitierung und Paginierung](#limitierung-und-paginierung)
  - [Aggregatfunktionen](#aggregatfunktionen)
  - [Subqueries](#subqueries)
  - [CTE (Common Table Expressions)](#cte-common-table-expressions)
  - [Abfrage-Kombinationen](#abfrage-kombinationen)
- [Insert Abfragen](#insert-abfragen)
- [Update Abfragen](#update-abfragen)
- [Delete Abfragen](#delete-abfragen)
- [JSON Funktionen](#json-funktionen)
- [Raw SQL Ausdrücke](#raw-sql-ausdrücke)
- [Debugging](#debugging)
- [Cache](#cache)

## Einführung

Der QueryBuilder ist ein leistungsfähiges PHP-Tool, das eine objektorientierte Schnittstelle zur Datenbankkommunikation bietet. Mit seiner fluent API können SQL-Abfragen auf einfache Weise erstellt und ausgeführt werden, ohne direkt SQL-Strings schreiben zu müssen.

Die Hauptvorteile des QueryBuilders sind:
- Typsichere Abfragen
- Automatischer Schutz vor SQL-Injection
- Einfaches Chaining der Methoden für komplexe Abfragen
- Wiederverwendbarkeit von Abfragekomponenten

## Grundlegende Verwendung

### Initialisierung

```php
// Initialisierung über Dependency Injection
public function __construct(protected readonly ConnectionManager $connectionManager)
{
    // ...
}

// Tabelle auswählen
$query = (new SelectQueryBuilder($connectionManager))->table('users');
```

### Verbindung auswählen

```php
// Verwende die Standardverbindung
$query = (new SelectQueryBuilder($connectionManager));

// Verwende eine bestimmte Verbindung
$query = (new SelectQueryBuilder($connectionManager))->connection('custom_connection');
```

### Abfrage ausführen

```php
// Statement zurückgeben
$statement = $query->execute();

// Bei Select: Alle Ergebnisse holen
$results = $query->get();

// Bei Select: Nur das erste Ergebnis holen
$firstResult = $query->first();
```

## Select Abfragen

### Spalten auswählen

```php
// Alle Spalten auswählen
$query->select();
// oder
$query->select(['*']);

// Bestimmte Spalten auswählen
$query->select(['id', 'name', 'email']);

// Einzelne Spalte hinzufügen
$query->addSelect('created_at');

// Spalte mit Alias
$query->addSelect('email', 'user_email');

// Raw Expression als Spalte
$query->addSelect($query->raw('COUNT(*) as user_count'));
```

### Where-Bedingungen

```php
// Einfache Where-Bedingung
$query->where('status', 'active');

// Mit Operator
$query->where('age', '>', 18);

// OR-Bedingung
$query->where('status', 'active')
      ->orWhere('status', 'pending');

// Gruppierte Bedingungen (Klammern)
$query->whereGroup(function($group) {
    $group->where('status', 'active')
          ->orWhere('status', 'pending');
});

// OR Gruppe
$query->where('is_admin', true)
      ->orWhereGroup(function($group) {
          $group->where('role', 'editor')
                ->where('department', 'content');
      });

// IN-Bedingung
$query->whereIn('id', [1, 2, 3, 4]);

// NOT IN-Bedingung
$query->whereNotIn('status', ['deleted', 'archived']);

// NULL-Überprüfung
$query->whereNull('deleted_at');
$query->whereNotNull('email_verified_at');

// BETWEEN-Bedingung
$query->whereBetween('age', 18, 65);

// EXISTS-Bedingung
$query->whereExists(function($subquery) {
    $subquery->table('orders')
             ->where('orders.user_id', 'users.id');
});

// NOT EXISTS-Bedingung
$query->whereNotExists(function($subquery) {
    $subquery->table('orders')
             ->where('orders.user_id', 'users.id');
});
```

### Joins

```php
// INNER JOIN
$query->join('orders', 'users.id = orders.user_id');

// LEFT JOIN
$query->leftJoin('orders', 'users.id = orders.user_id');

// RIGHT JOIN
$query->rightJoin('addresses', 'users.id = addresses.user_id');
```

### Gruppierung und Sortierung

```php
// GROUP BY
$query->groupBy('department');
$query->groupBy(['department', 'role']);

// GROUP BY mehrere Spalten
$query->groupByMultiple(['department', 'role', 'status']);

// HAVING
$query->having('count', '>', 5);

// ORDER BY
$query->orderBy('created_at', 'DESC');
$query->orderBy('name', 'ASC');
```

### Limitierung und Paginierung

```php
// LIMIT
$query->limit(10);

// OFFSET
$query->offset(20);

// Paginierung (kombinierte Methode)
$query->paginate(2, 15); // Seite 2 mit 15 Einträgen pro Seite
```

### Aggregatfunktionen

```php
// COUNT
$count = $query->count();
$count = $query->count('distinct id');

// SUM
$sum = $query->sum('amount');

// AVG
$average = $query->avg('rating');

// MIN
$min = $query->min('age');

// MAX
$max = $query->max('price');
```

### Subqueries

```php
// Subquery als Datenquelle
$subquery = $query->subquery(function($query) {
    $query->table('orders')
          ->select(['user_id', 'SUM(amount) as total_amount'])
          ->groupBy('user_id');
}, 'order_totals');

// Subquery in SELECT
$query->addSelect($query->subquery(function($query) {
    $query->table('comments')
          ->select($query->raw('COUNT(*)'))
          ->where('comments.post_id', 'posts.id');
}, 'comment_count'));

// Subquery in WHERE
$query->where('id', 'IN', $query->subquery(function($query) {
    $query->table('active_users')
          ->select(['id']);
}, 'active_user_ids'));
```

### CTE (Common Table Expressions)

```php
// Mit CTE (WITH-Klausel)
$query->with('recent_orders', function($query) {
    $query->table('orders')
          ->select(['user_id', 'created_at', 'amount'])
          ->where('created_at', '>', '2023-01-01')
          ->orderBy('created_at', 'DESC');
})
->table('users')
->select(['users.*', 'recent_orders.amount'])
->join('recent_orders', 'users.id = recent_orders.user_id');

// Mit Spaltenangabe
$query->with('order_stats', function($query) {
    $query->table('orders')
          ->select(['user_id', 'COUNT(*) as order_count'])
          ->groupBy('user_id');
}, ['user_id', 'order_count']);
```

### Abfrage-Kombinationen

```php
// UNION
$firstQuery = (new SelectQueryBuilder($connectionManager))
    ->table('users')
    ->select(['name', 'email'])
    ->where('department', 'sales');

$secondQuery = (new SelectQueryBuilder($connectionManager))
    ->table('archived_users')
    ->select(['name', 'email'])
    ->where('department', 'sales');

$firstQuery->union($secondQuery);

// UNION ALL (mit Duplikaten)
$firstQuery->union($secondQuery, true);

// INTERSECT
$firstQuery->intersect($secondQuery);

// EXCEPT
$firstQuery->except($secondQuery);
```

## Insert Abfragen

```php
$query = (new InsertQueryBuilder($connectionManager))
    ->table('users');

// Einzelner Datensatz
$query->values([
    'name' => 'Max Mustermann',
    'email' => 'max@example.com',
    'created_at' => new DateTime()
]);

// Mehrere Datensätze
$query->values([
    [
        'name' => 'Max Mustermann',
        'email' => 'max@example.com'
    ],
    [
        'name' => 'Erika Musterfrau',
        'email' => 'erika@example.com'
    ]
]);

// Ausführen und ID des eingefügten Datensatzes zurückgeben
$id = $query->executeAndGetId();

// Ausführen und Statement zurückgeben
$statement = $query->execute();

// Batch-Insert für große Datenmengen
$affectedRows = $query->executeBatch(100); // 100 Datensätze pro Batch
```

## Update Abfragen

```php
$query = (new UpdateQueryBuilder($connectionManager))
    ->table('users');

// Werte setzen
$query->values([
    'status' => 'inactive',
    'updated_at' => new DateTime()
]);

// Oder einzelne Werte setzen
$query->set('status', 'inactive')
      ->set('updated_at', new DateTime());

// Mit Raw Expression
$query->set('login_count', $query->raw('login_count + 1'));

// Where-Bedingungen (wie bei Select)
$query->where('id', 123);

// Ausführen
$statement = $query->execute();

// Bulk-Update für mehrere Datensätze
$records = [
    ['id' => 1, 'status' => 'active'],
    ['id' => 2, 'status' => 'inactive'],
    ['id' => 3, 'status' => 'pending']
];
$affectedRows = $query->bulkUpdate($records, 'id');

// Alle Datensätze aktualisieren (VORSICHT!)
$query->whereTrue()->execute();
```

## Delete Abfragen

```php
$query = (new DeleteQueryBuilder($connectionManager))
    ->table('users');

// Where-Bedingungen (wie bei Select)
$query->where('status', 'inactive');

// Ausführen
$statement = $query->execute();

// Alle Datensätze löschen (VORSICHT!)
$query->whereTrue()->execute();
```

## JSON Funktionen

Der QueryBuilder bietet spezielle Methoden für die Arbeit mit JSON-Spalten in MySQL:

```php
// Vergleich von JSON-Werten
$query->whereJson('data', '$.name', 'John');
$query->whereJson('data', '$.age', '>', 30);
$query->orWhereJson('data', '$.email', 'john@example.com');

// Prüfen, ob ein JSON-Pfad existiert
$query->whereJsonContains('data', '$.address');
$query->orWhereJsonContains('data', '$.phone');

// Prüfen, ob ein JSON-Array einen Wert enthält
$query->whereJsonArrayContains('data', '$.tags', 'important');
$query->orWhereJsonArrayContains('data', '$.categories', 'news');

// Prüfen der Länge eines JSON-Arrays
$query->whereJsonLength('data', '$.tags', '>', 3);
$query->orWhereJsonLength('data', '$.friends', '<', 5);

// String-Vergleich mit JSON-Textwerten
$query->whereJsonText('data', '$.name', 'LIKE', '%John%');
$query->orWhereJsonText('data', '$.description', 'LIKE', '%important%');

// Prüfen, ob ein JSON-Schlüssel existiert
$query->whereJsonHasKey('data', 'address');
$query->orWhereJsonHasKey('data', 'phone');
```

## Raw SQL Ausdrücke

Für komplexe SQL-Ausdrücke, die nicht durch die QueryBuilder-Methoden abgedeckt werden, können Raw-Expressions verwendet werden:

```php
// Raw Expression erstellen
$raw = new RawExpression('YEAR(created_at) = ?', [2023]);

// Raw Expression in WHERE-Klausel
$query->where($raw);

// Raw Expression in SELECT
$query->addSelect($query->raw('CONCAT(first_name, " ", last_name) AS full_name'));

// Raw Expression in GROUP BY
$query->groupBy($query->raw('YEAR(created_at)'));

// Raw Expression in ORDER BY
$query->orderBy($query->raw('RAND()'));

// Raw Expression in SET (für Updates)
$updateQuery->set('login_count', $updateQuery->raw('login_count + 1'));
```

## Debugging

```php
// Debug aktivieren (Abfrage wird protokolliert)
$query->debug();

// Debug mit Backtrace aktivieren
$query->debug(true);

// Formatierte SQL-Abfrage mit eingesetzten Parametern anzeigen
$formattedSql = $query->toFormattedSql();
```

## Cache

Der QueryBuilder verwendet einen Statement-Cache, um die Leistung zu verbessern. Der Cache wird automatisch verwaltet, aber bei Bedarf kann er für bestimmte Tabellen invalidiert werden:

```php
// Cache invalidieren (wird automatisch vom System bei Änderungen durchgeführt)
$cache->invalidateByPrefix('users');

// Cache-Statistiken abrufen
$statistics = $cache->getStatistics();
```

---

## Beispiele für komplexe Abfragen

### Komplexe SELECT-Abfrage mit JOIN, WHERE, GROUP BY und ORDER BY

```php
$query = (new SelectQueryBuilder($connectionManager))
    ->table('orders')
    ->select([
        'orders.id',
        'users.name AS customer_name',
        'order_items.product_id',
        $query->raw('SUM(order_items.quantity * products.price) AS total_amount')
    ])
    ->join('users', 'orders.user_id = users.id')
    ->join('order_items', 'orders.id = order_items.order_id')
    ->join('products', 'order_items.product_id = products.id')
    ->where('orders.status', 'completed')
    ->whereGroup(function($group) {
        $group->where('orders.created_at', '>=', '2023-01-01')
              ->orWhere('orders.is_priority', true);
    })
    ->groupBy(['orders.id', 'users.name', 'order_items.product_id'])
    ->having('total_amount', '>', 100)
    ->orderBy('total_amount', 'DESC')
    ->limit(10);

$results = $query->get();
```

### Komplexe Abfrage mit CTE und JSON-Filtern

```php
$query = (new SelectQueryBuilder($connectionManager))
    ->with('filtered_logs', function($query) {
        $query->table('logs')
              ->select(['id', 'user_id', 'data', 'created_at'])
              ->whereJson('data', '$.event_type', 'login')
              ->whereJson('data', '$.device.type', 'mobile')
              ->where('created_at', '>=', '2023-01-01');
    })
    ->table('users')
    ->select([
        'users.id',
        'users.name',
        'users.email',
        $query->raw('COUNT(filtered_logs.id) AS login_count')
    ])
    ->leftJoin('filtered_logs', 'users.id = filtered_logs.user_id')
    ->whereJsonHasKey('users.preferences', 'notifications')
    ->groupBy(['users.id', 'users.name', 'users.email'])
    ->having('login_count', '>', 5)
    ->orderBy('login_count', 'DESC');

$results = $query->get();
```

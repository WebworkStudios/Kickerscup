<?php

declare(strict_types=1);

/**
 * Routen-Konfiguration
 *
 * Hier können alle Routen für die Anwendung definiert werden.
 */

use App\Core\Application;

/* @var Application $app */
$router = $app->getRouter();

// Beispielrouten mit Namen
$router->get('/', function ($request) {
    return response()->html('<h1>Willkommen zum Football Manager Framework</h1>');
})->name('home');

$router->get('/api/status', function ($request) {
    return response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => time()
    ]);
})->name('api.status');

// Beispiel für eine parametrisierte Route
$router->get('/player/{id}', function ($request) {
    $id = $request->getParameters()['id'] ?? 0;
    return response()->json([
        'player_id' => $id,
        'name' => 'Beispielspieler ' . $id
    ]);
})->name('player.show');

// Gruppierte Routen mit Präfix
$router->prefix('/admin')->group(function ($router) {
    // Dashboard-Route
    $router->get('/', function ($request) {
        return response()->html('<h1>Admin-Dashboard</h1>');
    })->name('admin.dashboard');

    // Team-Verwaltung
    $router->get('/teams', function ($request) {
        return response()->html('<h1>Team-Verwaltung</h1>');
    })->name('admin.teams.index');

    $router->get('/teams/create', function ($request) {
        return response()->html('<h1>Team erstellen</h1>');
    })->name('admin.teams.create');

    $router->post('/teams', function ($request) {
        // Team erstellen...
        return response()->redirect(route('admin.teams.index'));
    })->name('admin.teams.store');

    $router->get('/teams/{id}', function ($request) {
        $id = $request->getParameters()['id'] ?? 0;
        return response()->html('<h1>Team #' . $id . ' bearbeiten</h1>');
    })->name('admin.teams.edit');

    $router->put('/teams/{id}', function ($request) {
        $id = $request->getParameters()['id'] ?? 0;
        // Team aktualisieren...
        return response()->redirect(route('admin.teams.index'));
    })->name('admin.teams.update');

    $router->delete('/teams/{id}', function ($request) {
        $id = $request->getParameters()['id'] ?? 0;
        // Team löschen...
        return response()->redirect(route('admin.teams.index'));
    })->name('admin.teams.destroy');
});

// API-Routen mit Domain und Präfix
$router->domain('api.example.com')->prefix('/v1')->group(function ($router) {
    $router->get('/teams', function ($request) {
        return response()->json([
            'teams' => [
                ['id' => 1, 'name' => 'FC Bayern München'],
                ['id' => 2, 'name' => 'Borussia Dortmund'],
                ['id' => 3, 'name' => 'RB Leipzig']
            ]
        ]);
    })->name('api.teams.index');

    $router->get('/teams/{id}', function ($request) {
        $id = $request->getParameters()['id'] ?? 0;
        return response()->json([
            'id' => $id,
            'name' => 'Team #' . $id,
            'players_count' => mt_rand(18, 25)
        ]);
    })->name('api.teams.show');
});

// Admin-Bereich mit Domain-Einschränkung
$router->domain('admin.example.com')->group(function ($router) {
    $router->get('/', function ($request) {
        return response()->html('<h1>Admin-Bereich</h1>');
    })->name('admin.home');

    $router->get('/dashboard', function ($request) {
        return response()->html('<h1>Admin-Dashboard</h1>');
    })->name('admin.dashboard.main');
});
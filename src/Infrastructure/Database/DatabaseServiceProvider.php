<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\Contracts\ResultHandlerInterface;
use App\Infrastructure\Database\QueryBuilder\DeleteQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\InsertQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\SelectQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\UpdateQueryBuilder;
use App\Infrastructure\Database\Result\ResultHandler;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere den Connection Manager
        $container->singleton(ConnectionManager::class);
        // Register the query debugger
        $container->singleton(Debug\QueryDebugger::class);
        // Register the statement cache
        $container->singleton(Cache\StatementCache::class, function () use ($container) {
            // Get max size from config if available
            $maxSize = 100; // Default
            if ($container->has('config')) {
                $config = $container->get('config');
                $maxSize = $config->get('database.statement_cache.max_size', 100);
            }

            return new Cache\StatementCache($maxSize);
        });

        // Registriere den Result Handler
        $container->bind(ResultHandlerInterface::class, ResultHandler::class);

        // Registriere die Query Builder
        $container->bind(SelectQueryBuilder::class);
        $container->bind(InsertQueryBuilder::class);
        $container->bind(UpdateQueryBuilder::class);
        $container->bind(DeleteQueryBuilder::class);
    }
}
<?php


declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Task;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\QueryBuilder\DeleteQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\InsertQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\SelectQueryBuilder;
use App\Infrastructure\Database\QueryBuilder\UpdateQueryBuilder;
use DateTime;

#[Injectable]
class TaskRepository
{
    protected string $table = 'tasks';

    public function __construct(
        private readonly ConnectionManager $connectionManager
    )
    {
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $rows = (new SelectQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return array_map(fn($row) => Task::fromArray($row), $rows);
    }

    public function findById(int $id): ?Task
    {
        $row = (new SelectQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        return Task::fromArray($row);
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        $rows = (new SelectQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->where('status', $status)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        return array_map(fn($row) => Task::fromArray($row), $rows);
    }

    public function create(Task $task): Task
    {
        $now = new DateTime();

        $data = [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'due_date' => $task->dueDate?->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s')
        ];

        $id = (new InsertQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->values($data)
            ->executeAndGetId();

        // Erstelle eine neue Task mit der generierten ID
        return new Task(
            id: (int)$id,
            title: $task->title,
            description: $task->description,
            status: $task->status,
            dueDate: $task->dueDate,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function update(Task $task): bool
    {
        if ($task->id === null) {
            return false;
        }

        $now = new DateTime();

        $data = [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'due_date' => $task->dueDate?->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s')
        ];

        $statement = (new UpdateQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->values($data)
            ->where('id', $task->id)
            ->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = (new DeleteQueryBuilder($this->connectionManager))
            ->table($this->table)
            ->where('id', $id)
            ->execute();

        return $statement->rowCount() > 0;
    }
}
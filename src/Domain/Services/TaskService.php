<?php


declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Entities\Task;
use App\Domain\Repositories\TaskRepository;
use App\Infrastructure\Container\Attributes\Injectable;
use DateTime;
use RuntimeException;

#[Injectable]
class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository
    )
    {
    }

    public function getAllTasks(int $limit = 100, int $offset = 0): array
    {
        return $this->taskRepository->findAll($limit, $offset);
    }

    public function getTaskById(int $id): ?Task
    {
        return $this->taskRepository->findById($id);
    }

    public function getTasksByStatus(string $status, int $limit = 100): array
    {
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            throw new RuntimeException("Invalid status: $status");
        }

        return $this->taskRepository->findByStatus($status, $limit);
    }

    public function createTask(string $title, ?string $description = null, ?string $dueDate = null): Task
    {
        $task = new Task(
            title: $title,
            description: $description,
            status: 'pending',
            dueDate: $dueDate ? new DateTime($dueDate) : null
        );

        return $this->taskRepository->create($task);
    }

    public function updateTask(int $id, array $data): bool
    {
        $task = $this->taskRepository->findById($id);

        if (!$task) {
            return false;
        }

        $updatedTask = new Task(
            id: $task->id,
            title: $data['title'] ?? $task->title,
            description: $data['description'] ?? $task->description,
            status: $data['status'] ?? $task->status,
            dueDate: isset($data['due_date']) ? new DateTime($data['due_date']) : $task->dueDate,
            createdAt: $task->createdAt
        );

        return $this->taskRepository->update($updatedTask);
    }

    public function deleteTask(int $id): bool
    {
        return $this->taskRepository->delete($id);
    }
}
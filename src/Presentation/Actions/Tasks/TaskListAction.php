<?php


declare(strict_types=1);

namespace App\Presentation\Actions\Tasks;

use App\Domain\Services\TaskService;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;

#[Injectable]
#[Get('/tasks', 'tasks.list')]
final class TaskListAction
{
    public function __construct(
        private readonly TaskService              $taskService,
        private readonly ResponseFactoryInterface $responseFactory
    )
    {
    }

    public function __invoke(RequestInterface $request): ResponseInterface
    {
        $limit = (int)$request->getQueryParam('limit', 100);
        $offset = (int)$request->getQueryParam('offset', 0);

        try {
            $tasks = $this->taskService->getAllTasks($limit, $offset);

            // Konvertiere Tasks zu Arrays für die JSON-Ausgabe
            $tasksArray = array_map(fn($task) => $task->toArray(), $tasks);

            return $this->responseFactory->createJson([
                'success' => true,
                'data' => [
                    'tasks' => $tasksArray
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->responseFactory->createServerError(
                "An error occurred while retrieving tasks: " . $e->getMessage()
            );
        }
    }
}
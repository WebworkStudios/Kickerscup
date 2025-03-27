<?php

declare(strict_types=1);

namespace App\Presentation\Actions\Tasks;

use App\Domain\Services\TaskService;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Routing\Attributes\Post;
use Throwable;

#[Injectable]
#[Post('/tasks', 'tasks.create')]
final class TaskCreateAction
{
    public function __construct(
        private readonly TaskService              $taskService,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ?LoggerInterface         $logger = null
    )
    {
    }

    public function __invoke(RequestInterface $request): ResponseInterface
    {
        try {
            // Optional Logger verwenden, wenn verfügbar
            if ($this->logger) {
                $this->logger->debug('TaskCreateAction: Processing request');
            }

            // Versuche, die Daten aus dem Anfragekörper zu extrahieren
            $data = $this->extractRequestData($request);

            // Validiere Eingabedaten
            if (empty($data) || !is_array($data)) {
                return $this->responseFactory->createJson([
                    'success' => false,
                    'error' => 'No valid data provided'
                ], 400);
            }

            if (empty($data['title'])) {
                return $this->responseFactory->createJson([
                    'success' => false,
                    'error' => 'Title is required'
                ], 400);
            }

            $task = $this->taskService->createTask(
                title: $data['title'],
                description: $data['description'] ?? null,
                dueDate: $data['due_date'] ?? null
            );

            return $this->responseFactory->createJson([
                'success' => true,
                'data' => [
                    'task' => $task->toArray()
                ]
            ], 201);
        } catch (Throwable $e) {
            if ($this->logger) {
                $this->logger->error('Error in TaskCreateAction', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ]);
            }

            return $this->responseFactory->createJson([
                'success' => false,
                'error' => "An error occurred while creating the task: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrahiert Daten aus dem Request, unterstützt JSON und Form-Daten
     */
    private function extractRequestData(RequestInterface $request): array
    {
        // 1. Versuche, JSON-Daten zu extrahieren
        $contentType = $request->getHeader('content-type');
        if ($contentType && str_contains(strtolower($contentType), 'application/json')) {
            $rawBody = $request->getRawBody();
            if (!empty($rawBody)) {
                $data = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }

        // 2. Versuche, Form-Daten zu extrahieren
        $postData = $request->getPostData();
        if (!empty($postData)) {
            return $postData;
        }

        // 3. Fallback: Leere Daten
        return [];
    }
}
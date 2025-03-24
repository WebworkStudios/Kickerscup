<?php

declare(strict_types=1);

namespace App\Presentation\Actions\Users;

use App\Domain\Entities\User;
use App\Domain\Services\UserService;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\RouteParam;

/**
 * Action to show a user by ID
 */
#[Injectable]
#[Get('/users/{id}', 'users.show')]
final class UserShowAction
{
    /**
     * Constructor
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory
    )
    {
    }

    /**
     * Handle the request
     *
     * @param int $id User ID
     * @param RequestInterface $request Current HTTP request
     * @return ResponseInterface
     */
    public function __invoke(
        #[RouteParam(regex: '\d+')]
        int              $id,
        RequestInterface $request
    ): ResponseInterface
    {
        try {
            // Da wir noch keinen UserService haben, erstellen wir Beispieldaten
            $user = $this->getMockUser($id);

            if (!$user) {
                return $this->responseFactory->createNotFound(
                    "User with ID {$id} not found."
                );
            }

            // Return JSON response with user data
            return $this->responseFactory->createJson([
                'success' => true,
                'data' => [
                    'user' => $this->transformUser($user)
                ]
            ]);
        } catch (\Throwable $e) {
            // Log the error and return error response
            return $this->responseFactory->createServerError(
                "An error occurred while retrieving the user: " . $e->getMessage()
            );
        }
    }

    /**
     * Transform user object to array
     *
     * @param object $user
     * @return array
     */
    private function transformUser(object $user): array
    {
        return [
            'id' => $user->id ?? 0,
            'name' => $user->name ?? 'Unknown',
            'email' => $user->email ?? 'unknown@example.com',
            'created_at' => $user->created_at ?? date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get a mock user for demonstration purposes
     *
     * @param int $id
     * @return object|null
     */
    private function getMockUser(int $id): ?object
    {
        $users = [
            1 => (object)[
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'created_at' => '2025-01-15 10:30:00'
            ],
            2 => (object)[
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'created_at' => '2025-02-20 14:45:00'
            ]
        ];

        return $users[$id] ?? null;
    }
}
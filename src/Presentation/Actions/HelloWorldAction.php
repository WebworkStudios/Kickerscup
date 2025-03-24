<?php


declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;

/**
 * Simple Hello World Action for testing routes
 */
#[Injectable]
#[Get('/hello', 'hello.world')]
final class HelloWorldAction
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
     * @return ResponseInterface
     */
    public function __invoke(): ResponseInterface
    {
        return $this->responseFactory->createJson([
            'message' => 'Hello, World!',
            'timestamp' => time()
        ]);
    }
}
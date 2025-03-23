<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Contracts;

interface SessionStoreInterface
{
    public function open(string $path, string $name): bool;

    public function close(): bool;

    public function read(string $id): string;

    public function write(string $id, string $data): bool;

    public function destroy(string $id): bool;

    public function gc(int $maxlifetime): bool;
}
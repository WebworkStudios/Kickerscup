<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Store;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;

#[Injectable]
class DefaultSessionStore implements SessionStoreInterface
{
    public function open(string $path, string $name): bool
    {
        return true; // PHP übernimmt das automatisch
    }

    public function close(): bool
    {
        return true; // PHP übernimmt das automatisch
    }

    public function read(string $id): string
    {
        return (string)session_decode(session_id($id));
    }

    public function write(string $id, string $data): bool
    {
        return true; // PHP übernimmt das automatisch
    }

    public function destroy(string $id): bool
    {
        return true; // PHP übernimmt das automatisch
    }

    public function gc(int $maxlifetime): bool
    {
        return true; // PHP übernimmt das automatisch
    }
}
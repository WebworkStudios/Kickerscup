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

        $oldId = session_id();
        session_id($id);
        $sessionData = '';

        if (session_start()) {
            $sessionData = session_encode() ?: '';
            session_abort();
        }

        session_id($oldId);

        return $sessionData;
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
<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Store;

use AllowDynamicProperties;
use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;
use App\Infrastructure\Session\SessionConfiguration;

#[AllowDynamicProperties] #[Injectable]
class DefaultSessionStore implements SessionStoreInterface
{
    public function __construct(SessionConfiguration $sessionConfig)
    {
        $this->sessionConfig = $sessionConfig;
    }

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
        // Nutzen wir die konfigurierte absolute Lebensdauer statt des übergebenen maxlifetime
        // Dies ermöglicht uns strengere Regeln als die PHP-Standardeinstellung

        // Da die Standard-PHP-GC automatisch läuft, müssen wir hier nichts Zusätzliches tun,
        // außer sicherzustellen, dass die PHP-Einstellung korrekt ist.
        ini_set('session.gc_maxlifetime', (string)$this->sessionConfig->absoluteLifetime);

        return true;
    }
}
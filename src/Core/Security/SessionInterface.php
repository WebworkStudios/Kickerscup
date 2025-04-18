<?php

declare(strict_types=1);

namespace App\Core\Security;

interface SessionInterface
{
    public function start(): void;
    public function regenerate(bool $deleteOldSession = true): void;
    public function destroy(): void;
    public function set(string $key, mixed $value): void;
    public function get(string $key, mixed $default = null): mixed;
    public function remove(string $key): void;
    public function has(string $key): bool;
    public function isExpired(): bool;
    public function updateActivity(): void;
    public function setFlash(string $key, mixed $value): void;
    public function getFlash(string $key, mixed $default = null): mixed;
    public function hasFlash(string $key): bool;
    public function setFingerprint(): void;
    public function validateFingerprint(): bool;

    // Neue Methoden
    public function isRateLimited(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool;
    public function rotateAfterLogin(int|string $userId): void;
    public function lock(int $timeout = 30): bool;
    public function unlock(): bool;
    public function setEncrypted(string $key, mixed $value): void;
    public function getEncrypted(string $key, mixed $default = null): mixed;
}
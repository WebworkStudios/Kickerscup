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
}
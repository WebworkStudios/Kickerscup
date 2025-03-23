<?php

declare(strict_types=1);

namespace App\Infrastructure\Session\Store;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;
use App\Infrastructure\Session\Contracts\UserSessionStoreInterface;
use App\Infrastructure\Session\SessionConfiguration;
use Redis;

#[Injectable]
class RedisSessionStore implements SessionStoreInterface, UserSessionStoreInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttl;

    public function __construct(
        Redis                $redis,
        SessionConfiguration $config,
        string               $prefix = 'sess:'
    )
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->ttl = $config->absoluteLifetime; // Ändere TTL auf die absolute Lebensdauer
    }

    public function open(string $path, string $name): bool
    {
        return $this->redis->isConnected();
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data !== false ? (string)$data : '';
    }

    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->redis->del($this->prefix . $id) > 0;
    }

    public function gc(int $maxlifetime): bool
    {
        // Redis übernimmt das Ablaufen automatisch
        return true;
    }

    /**
     * Destroys a specific session by ID
     *
     * @param string $sessionId The session ID to destroy
     * @return bool
     */
    public function destroySession(string $sessionId): bool
    {
        return $this->destroy($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateUserSessions(int|string $userId): bool
    {
        // Verwende Redis, um alle Sessions für einen Benutzer zu finden und zu löschen
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return true;
        }

        $userSessionPrefix = '_user_session';
        $invalidated = false;
        $currentSessionId = session_id();

        foreach ($keys as $key) {
            // Überspringe die aktuelle Session
            $sessionId = str_replace($this->prefix, '', $key);
            if ($sessionId === $currentSessionId) {
                continue;
            }

            $sessionData = $this->redis->get($key);
            if ($sessionData) {
                $data = @unserialize($sessionData);
                if (is_array($data) &&
                    isset($data[$userSessionPrefix]['user_id']) &&
                    $data[$userSessionPrefix]['user_id'] == $userId) {
                    $this->redis->del($key);
                    $invalidated = true;
                }
            }
        }

        return $invalidated;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserActiveSessions(int|string $userId): array
    {
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);
        $sessions = [];
        $userSessionPrefix = '_user_session';
        $currentSessionId = session_id();

        foreach ($keys as $key) {
            $sessionData = $this->redis->get($key);
            if ($sessionData) {
                $data = @unserialize($sessionData);
                if (is_array($data) &&
                    isset($data[$userSessionPrefix]['user_id']) &&
                    $data[$userSessionPrefix]['user_id'] == $userId) {
                    $sessionId = str_replace($this->prefix, '', $key);
                    $sessions[] = [
                        'id' => $sessionId,
                        'created_at' => $data['_created_at'] ?? null,
                        'last_activity' => $data['last_activity'] ?? null,
                        'user_agent' => $data[$userSessionPrefix]['user_agent'] ?? null,
                        'ip_address' => $data[$userSessionPrefix]['client_ip'] ?? null,
                        'bound_at' => $data[$userSessionPrefix]['bound_at'] ?? null,
                        'current' => ($currentSessionId === $sessionId)
                    ];
                }
            }
        }

        // Sortiere nach letzter Aktivität (neueste zuerst)
        usort($sessions, function ($a, $b) {
            $aActivity = $a['last_activity'] ?? 0;
            $bActivity = $b['last_activity'] ?? 0;
            return $bActivity <=> $aActivity;
        });

        return $sessions;
    }
}
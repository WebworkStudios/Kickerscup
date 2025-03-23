<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Contracts;

interface UserSessionStoreInterface extends SessionStoreInterface
{
    /**
     * Invalidiert alle Sessions eines bestimmten Benutzers
     *
     * @param int|string $userId Die Benutzer-ID
     * @return bool
     */
    public function invalidateUserSessions(int|string $userId): bool;

    /**
     * Gibt alle aktiven Sessions eines Benutzers zurück
     *
     * @param int|string $userId Die Benutzer-ID
     * @return array Liste aktiver Sessions mit Metadaten
     */
    public function getUserActiveSessions(int|string $userId): array;

    /**
     * Beendet eine spezifische Session anhand ihrer ID
     *
     * @param string $sessionId Die zu beendende Session-ID
     * @return bool
     */
    public function destroySession(string $sessionId): bool;
}
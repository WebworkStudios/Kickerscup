<?php


declare(strict_types=1);

namespace App\Infrastructure\Session;

class SessionConfiguration
{
    /**
     * @param string $name Der Session-Name
     * @param int $lifetime Die Lebensdauer des Session-Cookies in Sekunden
     * @param string $path Der Pfad für das Session-Cookie
     * @param string|null $domain Die Domain für das Session-Cookie (null für aktuelle Domain)
     * @param bool $secure Ob das Cookie nur über HTTPS gesendet werden soll
     * @param bool $httpOnly Ob das Cookie nur über HTTP zugänglich sein soll (nicht über JavaScript)
     * @param string $sameSite SameSite-Einstellung für das Cookie ('Lax', 'Strict', 'None')
     * @param int $gcProbability Garbage Collection-Wahrscheinlichkeit (0-100)
     * @param int $gcDivisor Garbage Collection-Divisor
     * @param int $gcMaxLifetime Maximale Lebensdauer für inaktive Sessions in Sekunden
     * @param int $idleTimeout Timeout für inaktive Sessions in Sekunden (0 = deaktiviert)
     * @param bool $fingerprintCheck Ob die Session-Fingerprint-Überprüfung aktiviert sein soll
     * @param bool $regenerateIdInterval Zeit in Sekunden, nach der die Session-ID automatisch regeneriert wird (0 = deaktiviert)
     */
    public function __construct(
        public readonly string  $name = 'app_session',
        public readonly int     $lifetime = 86400,         // 24 Stunden
        public readonly string  $path = '/',
        public readonly ?string $domain = null,
        public readonly bool    $secure = true,           // HTTPS empfohlen
        public readonly bool    $httpOnly = true,
        public readonly string  $sameSite = 'Lax',      // Lax als Standard (guter Kompromiss)
        public readonly int     $gcProbability = 1,        // Garbage Collection (1%)
        public readonly int     $gcDivisor = 100,
        public readonly int     $gcMaxLifetime = 7200,     // 2 Stunden
        public readonly int     $idleTimeout = 3600,       // 1 Stunde
        public readonly bool    $fingerprintCheck = true,
        public readonly int     $regenerateIdInterval = 1800 // 30 Minuten
    )
    {
    }
}
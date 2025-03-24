<?php


declare(strict_types=1);

namespace App\Infrastructure\Logging;

/**
 * Log-Level Enum
 *
 * PSR-3 kompatible Log-Levels
 */
enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    /**
     * Konvertiert einen String in ein LogLevel-Enum
     *
     * @param string $level Das zu konvertierende Level
     * @return self
     */
    public static function fromString(string $level): self
    {
        return self::tryFrom(strtolower($level)) ?? self::INFO;
    }

    /**
     * Prüft, ob ein Level mindestens so schwerwiegend ist wie das angegebene Level
     *
     * @param LogLevel $minLevel Das minimale Level
     * @return bool True, wenn das aktuelle Level mindestens so schwerwiegend ist wie das minimale Level
     */
    public function isAtLeast(LogLevel $minLevel): bool
    {
        return $this->getSeverity() >= $minLevel->getSeverity();
    }

    /**
     * Gibt die numerische Schwere des Levels zurück
     * höhere Werte bedeuten schwerere Probleme
     *
     * @return int
     */
    private function getSeverity(): int
    {
        return match ($this) {
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7,
        };
    }
}
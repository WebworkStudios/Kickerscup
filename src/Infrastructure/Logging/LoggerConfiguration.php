<?php


declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Infrastructure\Container\Attributes\Injectable;

/**
 * Konfiguration für den Logger
 */
#[Injectable]
readonly class LoggerConfiguration
{
    /**
     * Konstruktor
     *
     * @param string $defaultChannel Standardkanal für Logs
     * @param array<string, array{path: string, level: string}> $channels Konfigurierte Kanäle
     * @param string $dateFormat Format für das Datum in Logeinträgen
     * @param string $logFormat Format für Logeinträge
     * @param int $maxFiles Maximale Anzahl an Logdateien bei Rotation
     * @param string|int $filePermissions Berechtigungen für Logdateien (als String oder Oktanzahl)
     * @param string $logPath Standardpfad für Logdateien
     * @param bool $logStackTraces Ob Stack-Traces geloggt werden sollen
     */
    public function __construct(
        public string     $defaultChannel = 'application',
        public array      $channels = [
            'application' => [
                'path' => 'logs/application.log',
                'level' => 'debug',
            ],
            'error' => [
                'path' => 'logs/error.log',
                'level' => 'error',
            ],
            'security' => [
                'path' => 'logs/security.log',
                'level' => 'info',
            ],
        ],
        public string     $dateFormat = 'Y-m-d H:i:s',
        public string     $logFormat = '[{datetime}] [{level}] {message} {context}',
        public int        $maxFiles = 7,
        public string|int $filePermissions = 0644,
        public string     $logPath = 'logs',
        public bool       $logStackTraces = true,
    )
    {
    }
}
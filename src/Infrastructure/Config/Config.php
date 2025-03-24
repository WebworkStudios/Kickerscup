<?php /** @noinspection PhpUnused */


declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;

#[Injectable]
#[Singleton]
class Config
{
    /**
     * @var array
     */
    protected array $items = [];


    // Internale Eigenschaft für app.name z.B.
    private string $_appName;

    // Property Hook

    public string $appName {
        get {
            if (!isset($this->_appName)) {
                $this->_appName = $this->get('app.name', 'Default App Name');
            }
            return $this->_appName;
        }
        set {
            $this->_appName = $value;
            // Aktualisiere auch im items Array
            $this->items['app']['name'] = $value;
        }
    }

    /**
     * Lädt die Konfigurationsdateien
     */
    public function __construct()
    {
        $this->loadConfigFiles();
    }

    /**
     * Lädt alle Konfigurationsdateien aus dem config-Verzeichnis
     */
    protected function loadConfigFiles(): void
    {
        $configPath = APP_ROOT . '/config';
        $files = glob($configPath . '/*.php');

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    /**
     * Holt einen Konfigurationswert mit Punkt-Notation
     *
     * @param string $key Der Schlüssel in Punkt-Notation (z.B. 'app.name')
     * @param mixed $default Der Standardwert, wenn der Schlüssel nicht existiert
     * @return mixed Der Konfigurationswert
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $config = $this->items;

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }
}
<?php

declare(strict_types=1);

namespace App\Core\Cache;

/**
 * Dateibasierter Cache
 */
class FileCache implements Cache
{
    /**
     * Pfad zum Cache-Verzeichnis
     */
    private string $path;

    /**
     * Konstruktor
     *
     * @param string $path Pfad zum Cache-Verzeichnis
     */
    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/') . '/';

        // Sicherstellen, dass das Verzeichnis existiert
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->path . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        $content = $this->readFile($filename);

        if ($content === false) {
            return false;
        }

        $data = $this->unserialize($content);

        if (!is_array($data) || !isset($data['expires'])) {
            return false;
        }

        // Prüfen, ob der Cache abgelaufen ist
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Generiert einen Dateinamen für einen Schlüssel
     *
     * @param string $key Schlüssel
     * @return string Dateiname
     */
    private function getFilename(string $key): string
    {
        return $this->path . md5($key);
    }

    /**
     * Liest eine Datei
     *
     * @param string $filename Dateiname
     * @return string|false Inhalt der Datei oder false bei Fehler
     */
    private function readFile(string $filename): string|false
    {
        return file_get_contents($filename);
    }

    /**
     * Deserialisiert einen Wert
     *
     * @param string $value Serialisierter Wert
     * @return mixed Deserialisierter Wert
     */
    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return $default;
        }

        $content = $this->readFile($filename);

        if ($content === false) {
            return $default;
        }

        $data = $this->unserialize($content);

        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return $default;
        }

        // Prüfen, ob der Cache abgelaufen ist
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $filename = $this->getFilename($key);

        $data = [
            'value' => $value,
            'expires' => $ttl === null ? null : time() + $ttl
        ];

        return $this->writeFile($filename, $this->serialize($data));
    }

    /**
     * Schreibt eine Datei
     *
     * @param string $filename Dateiname
     * @param string $content Inhalt
     * @return bool True bei Erfolg, sonst false
     */
    private function writeFile(string $filename, string $content): bool
    {
        $directory = dirname($filename);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($filename, $content, LOCK_EX) !== false;
    }

    /**
     * Serialisiert einen Wert
     *
     * @param mixed $value Wert
     * @return string Serialisierter Wert
     */
    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function deletePattern(string $pattern): bool
    {
        $files = glob($this->path . $pattern);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $amount = 1): int|false
    {
        if (!$this->has($key)) {
            // Wenn der Schlüssel nicht existiert, initialisieren mit $amount
            $this->set($key, $amount);
            return $amount;
        }

        $value = $this->get($key);

        // Prüfen, ob der Wert inkrementiert werden kann
        if (!is_numeric($value)) {
            return false;
        }

        $value = (int)$value + $amount;
        $this->set($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $amount = 1): int|false
    {
        return $this->increment($key, -$amount);
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }
}
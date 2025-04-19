<?php

declare(strict_types=1);

namespace App\Core\Translation;

use App\Core\Cache\Cache;

/**
 * Translator-Klasse für API-Lokalisierung
 * Optimiert für JSON-Responses und API-Fehlermeldugen
 */
class Translator
{
    /**
     * Cache-Schlüssel-Präfix
     */
    private const CACHE_PREFIX = 'translation:';

    /**
     * Standard-Locale
     */
    private string $locale;

    /**
     * Fallback-Locale
     */
    private string $fallbackLocale;

    /**
     * Cache-Instanz
     */
    private ?Cache $cache;

    /**
     * Geladene Übersetzungen
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private array $loaded = [];

    /**
     * Basispfad für Übersetzungsdateien
     */
    private string $langPath;

    /**
     * Konstruktor
     *
     * @param string $locale Standard-Locale
     * @param string $fallbackLocale Fallback-Locale
     * @param Cache|null $cache Cache-Instanz (für bessere Performance)
     * @param string|null $langPath Benutzerdefinierter Pfad für Sprachdateien
     */
    public function __construct(
        string $locale = 'de',
        string $fallbackLocale = 'en',
        ?Cache $cache = null,
        ?string $langPath = null
    ) {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->cache = $cache;
        $this->langPath = $langPath ?? dirname(__DIR__, 3) . '/resources/lang';
    }

    /**
     * Gibt die aktuelle Sprache zurück
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Setzt die aktuelle Sprache
     *
     * @param string $locale Sprachcode (z.B. 'de', 'en')
     * @return self
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Prüft, ob eine Übersetzung existiert
     *
     * @param string $key Übersetzungsschlüssel
     * @param string|null $locale Optionale Sprache
     * @return bool True, wenn existiert
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;

        $parts = explode('.', $key);
        if (count($parts) < 2) {
            return false;
        }

        $file = array_shift($parts);
        $translations = $this->loadTranslationsForFile($file, $locale);

        // Zugriff auf verschachtelte Elemente
        $translation = $translations;
        foreach ($parts as $part) {
            if (!is_array($translation) || !isset($translation[$part])) {
                return false;
            }
            $translation = $translation[$part];
        }

        return is_string($translation);
    }

    /**
     * Lädt Übersetzungen für eine bestimmte Datei und Sprache
     *
     * @param string $file Dateiname (z.B. 'api')
     * @param string $locale Sprache
     * @return array Übersetzungen
     */
    private function loadTranslationsForFile(string $file, string $locale): array
    {
        $cacheKey = self::CACHE_PREFIX . $locale . '.' . $file;

        // Prüfen, ob bereits im Speicher
        if (isset($this->loaded[$locale][$file])) {
            return $this->loaded[$locale][$file];
        }

        // Versuchen, aus dem Cache zu laden
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $this->loaded[$locale][$file] = $cached;
                return $cached;
            }
        }

        // Aus Datei laden
        $path = $this->getTranslationPath($file, $locale);
        $translations = $this->loadTranslationFile($path);

        // Nicht gefunden? Fallback zur Ausweichsprache
        if (empty($translations) && $locale !== $this->fallbackLocale) {
            $fallbackPath = $this->getTranslationPath($file, $this->fallbackLocale);
            $translations = $this->loadTranslationFile($fallbackPath);
        }

        // Im Speicher und Cache speichern
        $this->loaded[$locale][$file] = $translations;
        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $translations, 3600); // 1 Stunde im Cache
        }

        return $translations;
    }

    /**
     * Übersetzt einen Schlüssel
     *
     * @param string $key Übersetzungsschlüssel (z.B. 'api.validation.required')
     * @param array $replace Zu ersetzende Parameter
     * @param string|null $locale Optionale Sprache für diese Übersetzung
     * @return string Übersetzte Zeichenkette
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        $parts = explode('.', $key);
        if (count($parts) < 2) {
            return $key; // Ungültiger Schlüssel
        }

        $file = array_shift($parts);
        $translations = $this->loadTranslationsForFile($file, $locale);

        // Zugriff auf verschachtelte Elemente
        $translation = $translations;
        foreach ($parts as $part) {
            if (!is_array($translation) || !isset($translation[$part])) {
                // Fallback zur Ausweichsprache
                if ($locale !== $this->fallbackLocale) {
                    return $this->get($key, $replace, $this->fallbackLocale);
                }
                return $key;
            }
            $translation = $translation[$part];
        }

        if (!is_string($translation)) {
            return $key;
        }

        return $this->replaceParameters($translation, $replace);
    }

    /**
     * Ersetzt Parameter in einer Übersetzung
     *
     * @param string $translation Übersetzungstext mit Platzhaltern
     * @param array $replace Zu ersetzende Parameter
     * @return string Text mit ersetzten Parametern
     */
    public function replaceParameters(string $translation, array $replace): string
    {
        if (empty($replace)) {
            return $translation;
        }

        $replacements = [];
        foreach ($replace as $key => $value) {
            $replacements[':' . $key] = $value;
        }

        return strtr($translation, $replacements);
    }

    /**
     * Gibt den Pfad zu einer Übersetzungsdatei zurück
     *
     * @param string $file Dateiname
     * @param string $locale Sprache
     * @return string Pfad
     */
    private function getTranslationPath(string $file, string $locale): string
    {
        return $this->langPath . '/' . $locale . '/' . $file . '.php';
    }

    /**
     * Lädt eine Übersetzungsdatei
     *
     * @param string $path Pfad zur Datei
     * @return array Übersetzungen
     */
    private function loadTranslationFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        return require $path;
    }

    /**
     * Gibt alle verfügbaren Übersetzungen für einen bestimmten Schlüssel zurück
     *
     * Nützlich für API-Clients, die mehrere Sprachen unterstützen
     *
     * @param string $key Übersetzungsschlüssel
     * @param array $locales Sprachen (oder alle unterstützten, wenn leer)
     * @param array $replace Zu ersetzende Parameter
     * @return array<string, string> Übersetzungen nach Sprache
     */
    public function getAll(string $key, array $locales = [], array $replace = []): array
    {
        // Wenn keine Sprachen angegeben, alle verfügbaren verwenden
        if (empty($locales)) {
            $locales = $this->getAvailableLocales();
        }

        $result = [];
        foreach ($locales as $locale) {
            $result[$locale] = $this->get($key, $replace, $locale);
        }

        return $result;
    }

    /**
     * Gibt alle verfügbaren Sprachen zurück
     *
     * @return array Verfügbare Sprachen
     */
    public function getAvailableLocales(): array
    {
        $locales = [];

        if (is_dir($this->langPath)) {
            $items = scandir($this->langPath);
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($this->langPath . '/' . $item)) {
                    $locales[] = $item;
                }
            }
        }

        return $locales;
    }

    /**
     * Setzt den Basispfad für Sprachdateien
     *
     * @param string $path Pfad zu den Sprachdateien
     * @return self
     */
    public function setLangPath(string $path): self
    {
        $this->langPath = rtrim($path, '/');
        return $this;
    }

    /**
     * Gibt den Basispfad für Sprachdateien zurück
     *
     * @return string
     */
    public function getLangPath(): string
    {
        return $this->langPath;
    }
}
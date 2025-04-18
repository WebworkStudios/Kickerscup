<?php


declare(strict_types=1);

namespace App\Core\Translation;

use App\Core\Cache\Cache;

/**
 * Translator-Klasse für Mehrsprachigkeit
 * Verwaltet Übersetzungen mit Caching für optimale Performance
 */
readonly class Translator
{
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
    private array $loaded;

    /**
     * Cache-Schlüssel-Präfix
     */
    private const CACHE_PREFIX = 'translation:';

    /**
     * Konstruktor
     *
     * @param string $locale Standard-Locale
     * @param string $fallbackLocale Fallback-Locale
     * @param Cache|null $cache Cache-Instanz (für bessere Performance)
     */
    public function __construct(
        string $locale = 'de',
        string $fallbackLocale = 'en',
        ?Cache $cache = null
    )
    {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->cache = $cache;
        $this->loaded = [];
    }

    /**
     * Setzt die aktuelle Sprache
     *
     * @param string $locale Sprachcode (z.B. 'de', 'en')
     * @return self
     */
    public function setLocale(string $locale): self
    {
        return new self($locale, $this->fallbackLocale, $this->cache);
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
     * Übersetzt einen Schlüssel
     *
     * @param string $key Übersetzungsschlüssel (z.B. 'validation.required')
     * @param array $replace Zu ersetzende Parameter
     * @param string|null $locale Optionale Sprache für diese Übersetzung
     * @return string Übersetzte Zeichenkette
     * @throws \Exception
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
    private function replaceParameters(string $translation, array $replace): string
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
     * Lädt Übersetzungen für eine bestimmte Datei und Sprache
     *
     * @param string $file Dateiname (z.B. 'validation')
     * @param string $locale Sprache
     * @return array Übersetzungen
     * @throws \Exception
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
     * Gibt den Pfad zu einer Übersetzungsdatei zurück
     *
     * @param string $file Dateiname
     * @param string $locale Sprache
     * @return string Pfad
     * @throws \Exception
     */
    private function getTranslationPath(string $file, string $locale): string
    {
        return resource_path("lang/$locale/$file.php");
    }

    /**
     * Prüft, ob eine Übersetzung existiert
     *
     * @param string $key Übersetzungsschlüssel
     * @param string|null $locale Optionale Sprache
     * @return bool True, wenn existiert
     * @throws \Exception
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
     * Übersetzt einen Schlüssel mit der Auswahl zwischen Singular und Plural
     *
     * @param string $key Übersetzungsschlüssel
     * @param int $number Anzahl für Pluralentscheidung
     * @param array $replace Zu ersetzende Parameter
     * @param string|null $locale Optionale Sprache
     * @return string Übersetzte Zeichenkette
     * @throws \Exception
     */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $replace['count'] = $number;

        $line = $this->get($key, $replace, $locale);

        // Einfacher Pluralisierungsmechanismus mit Pipe
        $segments = explode('|', $line);

        if (count($segments) === 1) {
            return $line;
        }

        // Nur zwei Fälle (singular|plural)
        if (count($segments) === 2) {
            return $number === 1 ? $segments[0] : $segments[1];
        }

        // Komplexere Pluralisierungsregeln könnten hier implementiert werden
        // Für jetzt nehmen wir an, dass der Index der richtige ist
        $index = min(abs($number), count($segments) - 1);
        return $segments[$index];
    }
}
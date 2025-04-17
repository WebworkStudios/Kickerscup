<?php


declare(strict_types=1);

namespace App\Core\Database;

/**
 * Paginator für Abfrageergebnisse
 */
class Paginator
{
    /**
     * Aktuelle Seite
     */
    private int $currentPage;

    /**
     * Anzahl der Einträge pro Seite
     */
    private int $perPage;

    /**
     * Gesamtanzahl der Einträge
     */
    private int $total;

    /**
     * Abfrageergebnisse
     */
    private array $items;

    /**
     * Basis-URL für Links
     */
    private ?string $baseUrl;

    /**
     * Konstruktor
     *
     * @param array $items Abfrageergebnisse
     * @param int $total Gesamtanzahl der Einträge
     * @param int $perPage Anzahl der Einträge pro Seite
     * @param int $currentPage Aktuelle Seite
     * @param string|null $baseUrl Basis-URL für Links
     */
    public function __construct(
        array   $items,
        int     $total,
        int     $perPage,
        int     $currentPage = 1,
        ?string $baseUrl = null
    )
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Gibt die aktuelle Seite zurück
     *
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Gibt die Anzahl der Einträge pro Seite zurück
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Gibt die Gesamtanzahl der Einträge zurück
     *
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Gibt die Abfrageergebnisse zurück
     *
     * @return array
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Gibt die Gesamtanzahl der Seiten zurück
     *
     * @return int
     */
    public function lastPage(): int
    {
        return (int)ceil($this->total / $this->perPage);
    }

    /**
     * Gibt zurück, ob es eine nächste Seite gibt
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Gibt die Nummer der nächsten Seite zurück
     *
     * @return int|null
     */
    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    /**
     * Gibt zurück, ob es eine vorherige Seite gibt
     *
     * @return bool
     */
    public function hasPreviousPages(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Gibt die Nummer der vorherigen Seite zurück
     *
     * @return int|null
     */
    public function previousPage(): ?int
    {
        return $this->hasPreviousPages() ? $this->currentPage - 1 : null;
    }

    /**
     * Erzeugt einen URL für eine bestimmte Seite
     *
     * @param int $page Seitennummer
     * @return string|null URL oder null, wenn keine Basis-URL gesetzt ist
     */
    public function url(int $page): ?string
    {
        if ($this->baseUrl === null) {
            return null;
        }

        $delimiter = str_contains($this->baseUrl, '?') ? '&' : '?';
        return "{$this->baseUrl}{$delimiter}page={$page}";
    }

    /**
     * Erzeugt ein Array mit Seiten-Links
     *
     * @param int $window Anzahl der Seiten links und rechts von der aktuellen Seite
     * @return array<int, string|null> Array mit Seitennummern => URLs
     */
    public function links(int $window = 2): array
    {
        if ($this->lastPage() <= 1) {
            return [];
        }

        $links = [];

        // Startseite
        $links[1] = $this->url(1);

        // Vorherige Seiten
        $start = max(2, $this->currentPage - $window);
        for ($i = $start; $i < $this->currentPage; $i++) {
            $links[$i] = $this->url($i);
        }

        // Aktuelle Seite
        if ($this->currentPage > 1 && $this->currentPage < $this->lastPage()) {
            $links[$this->currentPage] = $this->url($this->currentPage);
        }

        // Nächste Seiten
        $end = min($this->lastPage(), $this->currentPage + $window);
        for ($i = $this->currentPage + 1; $i <= $end; $i++) {
            $links[$i] = $this->url($i);
        }

        // Letzte Seite
        if ($this->lastPage() > $end) {
            $links[$this->lastPage()] = $this->url($this->lastPage());
        }

        return $links;
    }

    /**
     * Konvertiert den Paginator in ein assoziatives Array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage(),
            'next_page_url' => $this->hasMorePages() ? $this->url($this->nextPage()) : null,
            'prev_page_url' => $this->hasPreviousPages() ? $this->url($this->previousPage()) : null,
            'from' => ($this->currentPage - 1) * $this->perPage + 1,
            'to' => min($this->currentPage * $this->perPage, $this->total),
            'data' => $this->items,
        ];
    }
}
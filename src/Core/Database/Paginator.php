<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Paginator für Datenbankergebnisse
 *
 * Verwaltet die Paginierung von Datenbankergebnissen
 */
class Paginator
{
    /**
     * Ergebnisse für die aktuelle Seite
     */
    private array $items;

    /**
     * Gesamtanzahl der Einträge
     */
    private int $total;

    /**
     * Anzahl der Einträge pro Seite
     */
    private int $perPage;

    /**
     * Aktuelle Seite
     */
    private int $currentPage;

    /**
     * Basis-URL für Links
     */
    private ?string $baseUrl;

    /**
     * Konstruktor
     *
     * @param array $items Ergebnisse für die aktuelle Seite
     * @param int $total Gesamtanzahl der Einträge
     * @param int $perPage Anzahl der Einträge pro Seite
     * @param int $currentPage Aktuelle Seite
     * @param string|null $baseUrl Basis-URL für Links
     */
    public function __construct(array $items, int $total, int $perPage, int $currentPage, ?string $baseUrl = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Gibt die Ergebnisse für die aktuelle Seite zurück
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Gibt die Gesamtanzahl der Einträge zurück
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Gibt die Anzahl der Einträge pro Seite zurück
     *
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Gibt die aktuelle Seite zurück
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Gibt die letzte Seite zurück
     *
     * @return int
     */
    public function getLastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * Gibt zurück, ob es eine nächste Seite gibt
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    /**
     * Gibt zurück, ob es sich um die erste Seite handelt
     *
     * @return bool
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Gibt zurück, ob es sich um die letzte Seite handelt
     *
     * @return bool
     */
    public function isLastPage(): bool
    {
        return $this->currentPage === $this->getLastPage();
    }

    /**
     * Gibt den URL für eine Seite zurück
     *
     * @param int $page Seitenzahl
     * @return string|null
     */
    public function getUrl(int $page): ?string
    {
        if ($this->baseUrl === null) {
            return null;
        }

        $url = $this->baseUrl;
        $url .= (str_contains($url, '?')) ? '&' : '?';
        $url .= http_build_query(['page' => $page]);

        return $url;
    }

    /**
     * Gibt den URL für die nächste Seite zurück
     *
     * @return string|null
     */
    public function getNextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }

        return $this->getUrl($this->currentPage + 1);
    }

    /**
     * Gibt den URL für die vorherige Seite zurück
     *
     * @return string|null
     */
    public function getPreviousPageUrl(): ?string
    {
        if ($this->isFirstPage()) {
            return null;
        }

        return $this->getUrl($this->currentPage - 1);
    }

    /**
     * Generiert ein Array mit Links für die Paginierung
     *
     * @param int $onEachSide Anzahl der Links auf jeder Seite
     * @return array
     */
    public function getLinks(int $onEachSide = 3): array
    {
        $lastPage = $this->getLastPage();

        if ($lastPage <= 1) {
            return [];
        }

        $from = max(1, $this->currentPage - $onEachSide);
        $to = min($lastPage, $this->currentPage + $onEachSide);

        $links = [];

        for ($i = $from; $i <= $to; $i++) {
            $links[] = [
                'page' => $i,
                'url' => $this->getUrl($i),
                'active' => $i === $this->currentPage,
            ];
        }

        return $links;
    }
}
<?php


declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\DatabaseManager;
use App\Core\Database\Paginator;
use App\Entities\GuestbookEntry;

class GuestbookRepository
{
    public function __construct(
        private readonly DatabaseManager $db
    )
    {
    }

    public function findAll(): array
    {
        $entries = $this->db->table('guestbook')
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map(fn($entry) => GuestbookEntry::fromArray($entry), $entries);
    }

    public function findById(int $id): ?GuestbookEntry
    {
        $entry = $this->db->table('guestbook')
            ->where('id', '=', $id)
            ->first();

        return $entry ? GuestbookEntry::fromArray($entry) : null;
    }

    public function save(GuestbookEntry $entry): int
    {
        try {
            $data = [
                'name' => strip_tags($entry->name),  // HTML-Tags entfernen
                'email' => filter_var($entry->email, FILTER_SANITIZE_EMAIL),
                'message' => strip_tags($entry->message)
            ];

            $id = $this->db->table('guestbook')->insert('guestbook', $data);

            app_log('Neuer Gästebuch-Eintrag', [
                'id' => $id,
                'name' => $data['name'],
                'email' => $data['email']
            ], 'info');

            return $id;
        } catch (\Exception $e) {
            app_log('Fehler beim Speichern des Gästebuch-Eintrags', [
                'error' => $e->getMessage()
            ], 'error');

            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->table('guestbook')
                ->where('id', '=', $id)
                ->delete() > 0;
    }

    public function getEntriesPaginated(int $page = 1, int $perPage = 10): Paginator
    {
        return $this->db->table('guestbook')
            ->orderBy('created_at', 'DESC')
            ->paginate($page, $perPage, null, route('guestbook.show'));
    }

}
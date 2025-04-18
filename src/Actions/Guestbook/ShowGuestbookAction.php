<?php


declare(strict_types=1);

namespace App\Actions\Guestbook;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Repositories\GuestbookRepository;

class ShowGuestbookAction
{
    public function __construct(
        private readonly GuestbookRepository $repository
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $entries = $this->repository->findAll();

        // Hier würdest du ein Template-System verwenden
        // Der Einfachheit halber gebe ich hier direktes HTML zurück
        $content = '<!DOCTYPE html>
        <html>
        <head>
            <title>Gästebuch</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
                .entry { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
                .form { margin-top: 30px; }
                input, textarea { width: 100%; padding: 8px; margin-bottom: 10px; }
                button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
            </style>
        </head>
        <body>
            <h1>Gästebuch</h1>';

        if (!empty($entries)) {
            $content .= '<div class="entries">';
            foreach ($entries as $entry) {
                $content .= '<div class="entry">
                    <h3>' . e($entry->name) . ' <small>&lt;' . e($entry->email) . '&gt;</small></h3>
                    <p>' . nl2br(e($entry->message)) . '</p>
                    <small>' . e($entry->createdAt) . '</small>
                </div>';
            }
            $content .= '</div>';
        } else {
            $content .= '<p>Noch keine Einträge vorhanden.</p>';
        }

        // Formular hinzufügen
        $content .= '<div class="form">
            <h2>Neuer Eintrag</h2>
            <form method="post" action="' . route('guestbook.store') . '">
                ' . csrf_field() . '
                <div>
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div>
                    <label for="email">E-Mail:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div>
                    <label for="message">Nachricht:</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                <button type="submit">Eintragen</button>
            </form>
        </div>
        </body>
        </html>';

        return response()->html($content);
    }
}
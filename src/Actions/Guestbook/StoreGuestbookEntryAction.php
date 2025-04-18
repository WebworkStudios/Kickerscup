<?php

declare(strict_types=1);

namespace App\Actions\Guestbook;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Entities\GuestbookEntry;
use App\Repositories\GuestbookRepository;

class StoreGuestbookEntryAction
{
    public function __construct(
        private readonly GuestbookRepository $repository,
        private readonly Validator           $validator
    )
    {
    }

    // Hinzufügen der __invoke()-Methode
    public function __invoke(Request $request): Response
    {
        // Validierung
        $validation = $this->validator->validate($request->all(), [
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:255',
            'message' => 'required|string'
        ]);

        if ($validation->fails()) {
            // Normaler würde man mit Session-Flash arbeiten
            // Hier geben wir einfach eine Fehlermeldung zurück
            $errors = $validation->errors();
            $errorMessage = 'Bitte korrigieren Sie folgende Fehler:';

            foreach ($errors as $field => $fieldErrors) {
                $errorMessage .= "<br>- " . implode('<br>- ', $fieldErrors);
            }

            return response()->html("
                <h1>Fehler beim Speichern</h1>
                <p>$errorMessage</p>
                <a href='" . route('guestbook.show') . "'>Zurück zum Gästebuch</a>
            ");
        }

        // Eintrag erstellen und speichern
        $entry = GuestbookEntry::fromArray([
            'name' => $validation->get('name'),
            'email' => $validation->get('email'),
            'message' => $validation->get('message')
        ]);

        $this->repository->save($entry);

        // Zurück zum Gästebuch
        return response()->redirect(route('guestbook.show'));
    }
}
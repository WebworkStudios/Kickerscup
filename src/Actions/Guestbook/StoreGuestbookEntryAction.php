<?php

declare(strict_types=1);

namespace App\Actions\Guestbook;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Security\Security;
use App\Core\Security\Session;
use App\Entities\GuestbookEntry;
use App\Repositories\GuestbookRepository;

class StoreGuestbookEntryAction
{
    public function __construct(
        private readonly GuestbookRepository $repository,
        private readonly Validator $validator,
        private readonly Security $security,
        private readonly Session $session
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Rate Limiting
        if ($this->session->isRateLimited('guestbook_entries', 3, 3600)) {
            return $this->createErrorResponse(
                'Zu viele Einträge. Bitte warten Sie eine Stunde.',
                429
            );
        }

        // CSRF-Schutz
        $csrfToken = $request->getPostParam('csrf_token');

        if (!$this->security->getCsrf()->validateToken($csrfToken)) {
            return $this->createErrorResponse(
                'Ungültiges Sicherheitstoken. Bitte laden Sie die Seite neu.',
                403
            );
        }

        // Validierung
        $validation = $this->validator->validate($request->all(), [
            'name' => 'required|string|max:100|alpha_dash',
            'email' => 'required|email|max:255|unique:guestbook,email',
            'message' => [
                'required',
                'string',
                'min:10',
                'max:500',
                'regex:/^[^<>&]*$/' // Verhindert HTML/Script-Injection
            ]
        ], [
            'email.unique' => 'Diese E-Mail-Adresse wurde bereits verwendet.',
            'message.regex' => 'Die Nachricht enthält ungültige Zeichen.'
        ]);

        if ($validation->fails()) {
            return $this->createValidationErrorResponse($validation->errors());
        }

        // Spam-Erkennung
        $spamMessage = $this->checkForSpam(
            $validation->get('name'),
            $validation->get('email'),
            $validation->get('message')
        );

        if ($spamMessage !== null) {
            return $this->createErrorResponse($spamMessage, 403);
        }

        try {
            // Eintrag erstellen und speichern
            $entry = GuestbookEntry::fromArray([
                'name' => $this->sanitizeName($validation->get('name')),
                'email' => $validation->get('email'),
                'message' => $this->sanitizeMessage($validation->get('message'))
            ]);

            $entryId = $this->repository->save($entry);

            // Logging
            $this->logSuccessfulEntry($entryId, $entry);

            // Erfolgreiche Antwort
            return response()->json([
                'message' => 'Ihr Eintrag wurde erfolgreich gespeichert.',
                'redirect' => route('guestbook.show')
            ], 201);

        } catch (\Exception $e) {
            // Fehler-Logging
            $this->logError($e, $validation->validated());

            return $this->createErrorResponse(
                'Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
                500
            );
        }
    }

    private function createErrorResponse(string $message, int $statusCode): Response
    {
        return response()->json([
            'error' => $message
        ], $statusCode);
    }

    private function createValidationErrorResponse(array $errors): Response
    {
        return response()->json([
            'errors' => $errors,
            'message' => 'Bitte überprüfen Sie Ihre Eingaben.'
        ], 422);
    }

    private function checkForSpam(string $name, string $email, string $message): ?string
    {
        $spamPatterns = [
            '/http(s)?:\/\//',  // URLs
            '/\b(casino|viagra|loan)\b/i',  // Spam-Keywords
            '/[0-9]{3,}-[0-9]{3,}-[0-9]{3,}/'  // Telefonnummern
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return 'Ihr Eintrag wurde als Spam erkannt.';
            }
        }

        return null;
    }

    private function sanitizeName(string $name): string
    {
        // Entfernt potenzielle HTML-Tags und beschneidet Leerzeichen
        return trim(strip_tags($name));
    }

    private function sanitizeMessage(string $message): string
    {
        // Entfernt potenzielle HTML-Tags, beschneidet Leerzeichen
        return trim(strip_tags($message));
    }

    private function logSuccessfulEntry(int $entryId, GuestbookEntry $entry): void
    {
        app_log('Neuer Gästebuch-Eintrag', [
            'id' => $entryId,
            'name' => $entry->name,
            'email' => $entry->email
        ], 'info');
    }

    private function logError(\Exception $e, array $data): void
    {
        app_log('Fehler beim Gästebuch-Eintrag', [
            'error' => $e->getMessage(),
            'data' => $data,
            'trace' => $e->getTraceAsString()
        ], 'error');
    }
}
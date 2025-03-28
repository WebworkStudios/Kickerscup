<?php

declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Validation\RequestValidator;
use App\Infrastructure\Validation\ValidationException;

#[Injectable]
class TestFormAction
{
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected SessionInterface $session,
        protected RequestValidator $validator
    ) {
    }

    /**
     * Zeigt das Formular an
     */
    #[Get('/test/form', 'test.form')]
    public function showForm(RequestInterface $request): ResponseInterface
    {
        // Flash-Nachrichten für Fehler oder Erfolg aus der Session holen
        $errors = $this->session->getFlash('errors', []);
        $success = $this->session->getFlash('success');
        $oldInput = $this->session->getFlash('old', []);

        // HTML für das Formular generieren
        $html = $this->renderForm($oldInput, $errors, $success);

        return $this->responseFactory->createHtml($html);
    }

    /**
     * Verarbeitet das abgesendete Formular
     */
    #[Post('/test/form', 'test.form.submit')]
    public function handleForm(RequestInterface $request): ResponseInterface
    {
        // Validierungsregeln definieren
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
            'age' => 'numeric'
        ];

        try {
            // Validierung durchführen
            $isValid = $this->validator->validate($request, $rules, true);

            // Wenn die Validierung erfolgreich ist (Exception würde geworfen werden, wenn nicht)
            $this->session->flash('success', 'Das Formular wurde erfolgreich validiert!');

            // Hier könnte man weitere Verarbeitung der Daten durchführen
            // z.B. Speichern in einer Datenbank

            // Redirect zurück zum Formular
            return $this->responseFactory->createRedirect('/test/form');

        } catch (ValidationException $e) {
            // Fehler in die Session für die nächste Anfrage speichern
            $this->session->flash('errors', $e->getErrors());

            // Eingabedaten in die Session für die nächste Anfrage speichern
            $this->session->flash('old', array_merge(
                $request->getQueryParams(),
                $request->getPostData()
            ));

            // Redirect zurück zum Formular
            return $this->responseFactory->createRedirect('/test/form');
        }
    }

    /**
     * Rendert das HTML-Formular
     */
    private function renderForm(array $oldInput = [], array $errors = [], ?string $success = null): string
    {
        // Token für CSRF-Schutz
        $csrfToken = $this->session->get('_csrf_token', '');

        // Erfolgs- oder Fehlermeldungen anzeigen
        $alertHtml = '';
        if ($success) {
            $alertHtml = '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;">' .
                $success .
                '</div>';
        }

        // Formularfeld-Werte vorbereiten
        $nameValue = isset($oldInput['name']) ? htmlspecialchars($oldInput['name']) : '';
        $emailValue = isset($oldInput['email']) ? htmlspecialchars($oldInput['email']) : '';
        $ageValue = isset($oldInput['age']) ? htmlspecialchars($oldInput['age']) : '';

        // Fehlermeldungen für die Felder rendern
        $nameErrors = $this->renderErrors($errors, 'name');
        $emailErrors = $this->renderErrors($errors, 'email');
        $ageErrors = $this->renderErrors($errors, 'age');

        // Formular HTML
        return '<!DOCTYPE html>
        <html lang="">
        <head>
            <title>Test Formular</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .form-group {
                    margin-bottom: 15px;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                input {
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .error {
                    color: #dc3545;
                    font-size: 0.9em;
                    margin-top: 5px;
                }
                button {
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                button:hover {
                    background-color: #45a049;
                }
            </style>
        </head>
        <body>
            <h1>Test Formular</h1>
            
            ' . $alertHtml . '
            
            <form method="POST" action="/test/form">
                <!-- CSRF Token -->
                <input type="hidden" name="_csrf_token" value="' . $csrfToken . '">
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" value="' . $nameValue . '">
                    ' . $nameErrors . '
                </div>
                
                <div class="form-group">
                    <label for="email">E-Mail:</label>
                    <input type="email" id="email" name="email" value="' . $emailValue . '">
                    ' . $emailErrors . '
                </div>
                
                <div class="form-group">
                    <label for="age">Alter:</label>
                    <input type="number" id="age" name="age" value="' . $ageValue . '">
                    ' . $ageErrors . '
                </div>
                
                <button type="submit">Absenden</button>
            </form>
        </body>
        </html>';
    }

    /**
     * Rendert Fehlermeldungen für ein Feld
     */
    private function renderErrors(array $errors, string $field): string
    {
        if (!isset($errors[$field])) {
            return '';
        }

        $errorHtml = '';
        foreach ($errors[$field] as $error) {
            $errorHtml .= '<div class="error">' . htmlspecialchars($error) . '</div>';
        }

        return $errorHtml;
    }
}
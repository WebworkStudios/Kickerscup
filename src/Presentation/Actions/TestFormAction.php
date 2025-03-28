<?php

declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Validation\RequestValidator;
use App\Infrastructure\Validation\ValidationException;

#[Injectable]
class TestFormAction
{
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected SessionInterface $session,
        protected RequestValidator $validator,
        protected ContainerInterface $container
    ) {
    }

    /**
     * Zeigt das Formular an
     */
    #[Get('/test/form', 'test.form')]
    public function showForm(RequestInterface $request): ResponseInterface
    {
        // CSRF-Service aus dem Container holen
        $csrfService = $this->container->get(CsrfProtectionInterface::class);

        // Token generieren
        $csrfToken = $csrfService->generateToken();

        // Flash-Nachrichten holen
        $errors = $flashMessage->get('errors', []);
        $success = $flashMessage->get('success');
        $oldInput = $flashMessage->get('old', []);

        // HTML für das Formular generieren
        $html = $this->renderForm($oldInput, $errors, $success, $csrfToken);

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

        // FlashMessage direkt aus dem Container holen
        $flashMessage = $this->container->get(FlashMessageInterface::class);

        try {
            // Validierung durchführen
            $isValid = $this->validator->validate($request, $rules, true);

            // Wenn die Validierung erfolgreich ist
            $name = $request->getPostParam('name', 'Besucher');
            $message = "Formular erfolgreich gesendet! Hallo $name!";

            // Flash-Nachricht direkt über FlashMessage setzen
            $flashMessage->add('success', $message);

            // Session-Daten sichern
            $this->session->flush();

            // Redirect zurück zum Formular
            return $this->responseFactory->createRedirect('/test/form');

        } catch (ValidationException $e) {
            // Flash-Nachrichten direkt über FlashMessage setzen
            $flashMessage->add('errors', $e->getErrors());

            // Eingabedaten speichern
            $flashMessage->add('old', array_merge(
                $request->getQueryParams(),
                $request->getPostData()
            ));

            // Session-Daten sichern
            $this->session->flush();

            // Redirect zurück zum Formular
            return $this->responseFactory->createRedirect('/test/form');
        }
    }

    /**
     * Rendert das HTML-Formular
     */
    private function renderForm(array $oldInput = [], array $errors = [], ?string $success = null, string $csrfToken = ''): string
    {
        // Session-Status überprüfen
        $sessionStatus = '';
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionStatus = '<p style="color: green;">Session aktiv (ID: ' . session_id() . ')</p>';
        } else {
            $sessionStatus = '<p style="color: red;">Keine aktive Session!</p>';
        }

        // Erfolgs- oder Fehlermeldungen anzeigen
        $alertHtml = '';
        if ($success) {
            $alertHtml .= '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;">' .
                htmlspecialchars($success) .
                '</div>';
        }

        // Globale Fehler anzeigen
        if (!empty($errors) && is_array($errors)) {
            $alertHtml .= '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>Fehler im Formular:</strong>
                <ul style="margin-top: 5px; margin-bottom: 0;">';

            foreach ($errors as $field => $fieldErrors) {
                if (is_array($fieldErrors)) {
                    foreach ($fieldErrors as $error) {
                        $alertHtml .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                } else {
                    $alertHtml .= '<li>' . htmlspecialchars((string)$fieldErrors) . '</li>';
                }
            }

            $alertHtml .= '</ul></div>';
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
        $html = '<!DOCTYPE html>
        <html lang="de">
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
                .debug-panel {
                    margin-top: 30px;
                    border-top: 1px solid #ddd;
                    padding-top: 15px;
                }
                .debug-panel pre {
                    background-color: #f8f9fa;
                    padding: 10px;
                    border-radius: 4px;
                    overflow: auto;
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
            
            <div class="debug-panel">
                <h2>Debug-Informationen</h2>
                ' . $sessionStatus . '
                <h3>Session-Inhalt:</h3>
                <pre>' . htmlspecialchars(print_r($_SESSION ?? [], true)) . '</pre>
                
                <h3>Flash-Nachrichten in der Session:</h3>
                <pre>Errors: ' . htmlspecialchars(print_r($errors, true)) . '</pre>
                <pre>Success: ' . htmlspecialchars(print_r($success, true)) . '</pre>
                <pre>Old Input: ' . htmlspecialchars(print_r($oldInput, true)) . '</pre>
                
                <h3>Request-Daten:</h3>
                <pre>POST: ' . htmlspecialchars(print_r($_POST, true)) . '</pre>
            </div>
        </body>
        </html>';

        return $html;
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
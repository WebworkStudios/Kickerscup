<?php

declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Validation\ValidationException;

#[Injectable]
class TestFormAction
{
    // Konstanten für Session-Keys
    private const string CSRF_TOKEN = 'manual_csrf_token';
    private const string ERRORS_KEY = 'manual_form_errors';
    private const string SUCCESS_KEY = 'manual_form_success';
    private const string OLD_INPUT_KEY = 'manual_form_input';

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory
    ) {
        // Starten Sie die Session, falls noch nicht gestartet
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Zeigt das Formular an
     */
    #[Get('/test/form', 'test.form')]
    public function showForm(RequestInterface $request): ResponseInterface
    {
        // CSRF-Token manuell generieren und in der Session speichern
        if (!isset($_SESSION[self::CSRF_TOKEN])) {
            $_SESSION[self::CSRF_TOKEN] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION[self::CSRF_TOKEN];

        // Daten aus der Session lesen
        $errors = $_SESSION[self::ERRORS_KEY] ?? [];
        $success = $_SESSION[self::SUCCESS_KEY] ?? null;
        $oldInput = $_SESSION[self::OLD_INPUT_KEY] ?? [];

        // Nach dem Lesen aus der Session löschen
        unset($_SESSION[self::ERRORS_KEY]);
        unset($_SESSION[self::SUCCESS_KEY]);
        unset($_SESSION[self::OLD_INPUT_KEY]);

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
        // CSRF-Token überprüfen
        $submittedToken = $request->getPostParam('_csrf_token');
        $storedToken = $_SESSION[self::CSRF_TOKEN] ?? '';

        if (empty($submittedToken) || $submittedToken !== $storedToken) {
            $_SESSION[self::ERRORS_KEY] = ['global' => ['CSRF-Token ist ungültig. Bitte laden Sie die Seite neu.']];
            return $this->responseFactory->createRedirect('/test/form');
        }

        // Manuelle Validierung durchführen
        $name = $request->getPostParam('name', '');
        $email = $request->getPostParam('email', '');
        $age = $request->getPostParam('age', '');

        $errors = [];

        // Name validieren
        if (empty($name)) {
            $errors['name'] = ['Name ist erforderlich.'];
        }

        // Email validieren
        if (empty($email)) {
            $errors['email'] = ['E-Mail ist erforderlich.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Bitte geben Sie eine gültige E-Mail-Adresse ein.'];
        }

        // Age validieren (falls vorhanden)
        if (!empty($age) && !is_numeric($age)) {
            $errors['age'] = ['Alter muss eine Zahl sein.'];
        }

        // Eingabedaten für den Fall eines Fehlers speichern
        $inputData = [
            'name' => $name,
            'email' => $email,
            'age' => $age
        ];

        // Bei Fehlern
        if (!empty($errors)) {
            $_SESSION[self::ERRORS_KEY] = $errors;
            $_SESSION[self::OLD_INPUT_KEY] = $inputData;
            return $this->responseFactory->createRedirect('/test/form');
        }

        // Bei erfolgreicher Validierung
        $_SESSION[self::SUCCESS_KEY] = "Formular erfolgreich gesendet! Hallo $name!";

        // Redirect zurück zum Formular
        return $this->responseFactory->createRedirect('/test/form');
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
        if (!empty($errors)) {
            $alertHtml .= '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>Fehler im Formular:</strong>
                <ul style="margin-top: 5px; margin-bottom: 0;">';

            // Zeige globale Fehler
            if (isset($errors['global'])) {
                foreach ($errors['global'] as $error) {
                    $alertHtml .= '<li>' . htmlspecialchars($error) . '</li>';
                }
            }

            $alertHtml .= '</ul></div>';
        }

        // Formularfeld-Werte vorbereiten
        $nameValue = isset($oldInput['name']) ? htmlspecialchars($oldInput['name']) : '';
        $emailValue = isset($oldInput['email']) ? htmlspecialchars($oldInput['email']) : '';
        $ageValue = isset($oldInput['age']) ? htmlspecialchars($oldInput['age']) : '';

        // Fehlermeldungen für die Felder
        $nameErrors = isset($errors['name']) ? $this->renderFieldErrors($errors['name']) : '';
        $emailErrors = isset($errors['email']) ? $this->renderFieldErrors($errors['email']) : '';
        $ageErrors = isset($errors['age']) ? $this->renderFieldErrors($errors['age']) : '';

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
                
                <h3>Aktuelle Formular-Werte:</h3>
                <pre>Errors: ' . htmlspecialchars(print_r($errors, true)) . '</pre>
                <pre>Success: ' . htmlspecialchars($success ?? '') . '</pre>
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
    private function renderFieldErrors(array $errors): string
    {
        $html = '';
        foreach ($errors as $error) {
            $html .= '<div class="error">' . htmlspecialchars($error) . '</div>';
        }
        return $html;
    }
}
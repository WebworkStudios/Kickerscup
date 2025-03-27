<?php

declare(strict_types=1);

namespace App\Presentation\Actions\Tests;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Validation\RequestValidator;
use App\Infrastructure\Validation\ValidationException;

#[Injectable]
final class ValidationTestAction
{
    /**
     * Constructor
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly RequestValidator         $validator
    )
    {
    }

    #[Get('/test/validation', 'test.validation')]
    public function showForm(RequestInterface $request): ResponseInterface
    {
        // Render das HTML-Formular ohne Fehlermeldungen und ohne Eingabedaten
        $html = $this->renderFormHtml();
        return $this->responseFactory->createHtml($html);
    }



    #[Post('/test/validation', 'test.validation.process')]
    public function processForm(RequestInterface $request): ResponseInterface
    {
        // Validierungsregeln definieren
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric'
        ];

        // Daten erfassen
        $postData = $request->getPostData();

        // Validierung durchführen
        $validator = $this->validator->getValidator();
        $isValid = $validator->validate($postData, $rules);

        // Wenn die Validierung fehlschlägt, zeige Fehler an
        if (!$isValid) {
            $errors = $validator->getErrors();
            error_log('Validation failed with errors: ' . json_encode($errors));
            $html = $this->renderFormHtml($postData, $errors);
            return $this->responseFactory->createHtml($html, 422);
        }

        // Wenn die Validierung erfolgreich ist
        $successHtml = $this->renderSuccessHtml($postData);
        return $this->responseFactory->createHtml($successHtml);
    }

    /**
     * Neue vereinfachte Methode zum Rendern des Formulars mit Fehlern
     */
    private function renderFormWithErrors(array $data, array $errors): string
    {
        // Werte mit Sicherheitsschutz
        $name = htmlspecialchars($data['name'] ?? '', ENT_QUOTES);
        $email = htmlspecialchars($data['email'] ?? '', ENT_QUOTES);
        $age = htmlspecialchars($data['age'] ?? '', ENT_QUOTES);

        // Fehler-HTML für jedes Feld
        $nameError = !empty($errors['name']) ? '<p style="color: red; font-weight: bold;">' . implode('<br>', $errors['name']) . '</p>' : '';
        $emailError = !empty($errors['email']) ? '<p style="color: red; font-weight: bold;">' . implode('<br>', $errors['email']) . '</p>' : '';
        $ageError = !empty($errors['age']) ? '<p style="color: red; font-weight: bold;">' . implode('<br>', $errors['age']) . '</p>' : '';

        // Einfaches HTML-Formular
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Validierungstest</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .field { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Validierungstest</h1>
    
    <form method="post" action="/test/validation">
        <div class="field">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" value="$name">
            $nameError
        </div>
        
        <div class="field">
            <label for="email">E-Mail:</label>
            <input type="text" id="email" name="email" value="$email">
            $emailError
        </div>
        
        <div class="field">
            <label for="age">Alter:</label>
            <input type="text" id="age" name="age" value="$age">
            $ageError
        </div>
        
        <button type="submit">Absenden</button>
    </form>
</body>
</html>
HTML;
    }

    // src/Presentation/Actions/Tests/ValidationTestAction.php

    /**
     * Rendert das HTML-Formular mit optionalen Fehler- und Eingabedaten
     */
    private function renderFormHtml(array $inputData = [], array $errors = []): string
    {
        $nameValue = htmlspecialchars($inputData['name'] ?? '', ENT_QUOTES);
        $emailValue = htmlspecialchars($inputData['email'] ?? '', ENT_QUOTES);
        $ageValue = htmlspecialchars($inputData['age'] ?? '', ENT_QUOTES);

        // Debug-Ausgabe für Fehler
        error_log('Rendering form with errors: ' . json_encode($errors));

        $nameErrors = isset($errors['name']) ? '<div class="error">' . implode('<br>', $errors['name']) . '</div>' : '';
        $emailErrors = isset($errors['email']) ? '<div class="error">' . implode('<br>', $errors['email']) . '</div>' : '';
        $ageErrors = isset($errors['age']) ? '<div class="error">' . implode('<br>', $errors['age']) . '</div>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validierungstest</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .error { color: #f44336; margin-top: 5px; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>Validierungstest</h1>
    <p>Dieses Formular testet die serverseitige Validierung.</p>
    
    <form method="post" action="/test/validation">
        <div class="form-group">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" value="$nameValue">
            $nameErrors
        </div>
        
        <div class="form-group">
            <label for="email">E-Mail:</label>
            <input type="text" id="email" name="email" value="$emailValue">
            $emailErrors
        </div>
        
        <div class="form-group">
            <label for="age">Alter:</label>
            <input type="text" id="age" name="age" value="$ageValue">
            $ageErrors
        </div>
        
        <button type="submit">Absenden</button>
    </form>
</body>
</html>
HTML;
    }

    /**
     * Rendert die Erfolgsseite mit den validierten Daten
     */
    private function renderSuccessHtml(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? '', ENT_QUOTES);
        $email = htmlspecialchars($data['email'] ?? '', ENT_QUOTES);
        $age = htmlspecialchars($data['age'] ?? '', ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validierung erfolgreich</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success-box { background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        h1 { color: #3c763d; }
        .data-item { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .back-link { display: inline-block; margin-top: 20px; color: #337ab7; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="success-box">
        <h1>Validierung erfolgreich!</h1>
        <p>Alle Eingaben wurden erfolgreich validiert.</p>
    </div>
    
    <h2>Eingabedaten:</h2>
    <div class="data-item">
        <span class="label">Name:</span> $name
    </div>
    <div class="data-item">
        <span class="label">E-Mail:</span> $email
    </div>
    <div class="data-item">
        <span class="label">Alter:</span> $age
    </div>
    
    <a href="/test/validation" class="back-link">Zurück zum Formular</a>
</body>
</html>
HTML;
    }

    /**
     * Gibt die Validierungsregeln für diesen Request zurück
     */
    public function getValidationRules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric'
        ];
    }
}
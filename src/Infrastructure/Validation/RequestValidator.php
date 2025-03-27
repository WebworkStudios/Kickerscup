<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;

#[Injectable]
class RequestValidator
{
    /**
     * Konstruktor
     */
    public function __construct(
        protected ValidatorInterface $validator
    )
    {
    }

    /**
     * Validiert einen Request
     *
     * @param RequestInterface $request Der zu validierende Request
     * @param array<string, mixed> $rules Die Validierungsregeln
     * @param bool $throwOnFailure
     * @return bool True, wenn die Validierung erfolgreich ist
     */
    // src/Infrastructure/Validation/RequestValidator.php
// Überprüfen und korrigieren wir die validate-Methode

    // src/Infrastructure/Validation/RequestValidator.php
// Fügen wir Debug-Logging hinzu

    // src/Infrastructure/Validation/RequestValidator.php

    public function validate(
        RequestInterface $request,
        array            $rules,
        bool             $throwOnFailure = false
    ): bool
    {
        // Daten zum Validieren sammeln
        $data = [];

        // POST-Daten hinzufügen
        $postData = $request->getPostData();
        if (!empty($postData)) {
            $data = array_merge($data, $postData);
        }

        // Query-Parameter hinzufügen
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $data = array_merge($data, $queryParams);
        }

        // Wichtig: Auch für leere Felder Schlüssel erstellen
        // Dies stellt sicher, dass required-Validierungen auch greifen, wenn
        // im Formular alle Felder leer gelassen wurden
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
        }

        // JSON-Body hinzufügen, wenn vorhanden
        if ($request->isJson()) {
            $jsonData = $request->getJsonBody() ?: [];
            $data = array_merge($data, $jsonData);
        }

        // Validierung durchführen
        $result = $this->validator->validate($data, $rules);

        // Bei Fehler und wenn Exception gewünscht ist
        if (!$result && $throwOnFailure) {
            throw ValidationException::withErrors(
                'Die Validierung ist fehlgeschlagen.',
                $this->validator->getErrors()
            );
        }

        return $result;
    }

    /**
     * Gibt den Validator zurück
     *
     * @return ValidatorInterface
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * Gibt die Validierungsfehler zurück
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->validator->getErrors();
    }
}
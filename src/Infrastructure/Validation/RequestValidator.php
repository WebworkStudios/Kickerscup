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
     * @param bool $includeFiles Ob auch hochgeladene Dateien validiert werden sollen
     * @return bool True, wenn die Validierung erfolgreich ist
     * @throws ValidationException Wenn die Validierung fehlschlägt und $throwOnFailure true ist
     */
    public function validate(
        RequestInterface $request,
        array            $rules,
        bool             $throwOnFailure = false
    ): bool
    {
        // Daten zum Validieren sammeln (POST + GET)
        $data = array_merge(
            $request->getQueryParams(),
            $request->getPostData()
        );

        // Json-Body hinzufügen, wenn vorhanden
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
<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;

#[Injectable]
class RequestValidator
{
    /**
     * Konstruktor
     */
    public function __construct(
        protected ValidatorInterface $validator,
        protected ContainerInterface $container
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

    public function validate(
        RequestInterface $request,
        array            $rules,
        bool             $throwOnFailure = false,
        bool             $validateCsrf = false
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

        // JSON-Body hinzufügen, wenn vorhanden
        if ($request->isJson()) {
            $jsonData = $request->getJsonBody() ?: [];
            $data = array_merge($data, $jsonData);
        }

        // Wichtig: Für jedes in den Regeln erwähnte Feld sicherstellen, dass es im Datensatz existiert
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
        }

        // CSRF-Validierung, wenn gewünscht
        if ($validateCsrf && $this->container->has(CsrfProtectionInterface::class)) {
            try {
                $csrfService = $this->container->get(CsrfProtectionInterface::class);

                // Prüfen, ob der Request geschützt werden sollte
                if ($csrfService->shouldProtectRequest($request)) {
                    // Token aus dem Request extrahieren
                    $token = $this->getTokenFromRequest($request);

                    if (!$token || !$csrfService->validateToken($token)) {
                        if ($throwOnFailure) {
                            throw ValidationException::withErrors(
                                'CSRF-Validierung fehlgeschlagen.',
                                ['_csrf_token' => ['CSRF-Token ungültig oder nicht vorhanden.']]
                            );
                        }
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                // Logger würde hier helfen, um das Problem zu diagnostizieren
                if ($throwOnFailure) {
                    throw ValidationException::withErrors(
                        'CSRF-Validierung fehlgeschlagen: ' . $e->getMessage(),
                        ['_csrf_token' => ['Fehler bei der CSRF-Validierung.']]
                    );
                }
                return false;
            }
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

    /**
     * Extrahiert das CSRF-Token aus dem Request
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return string|null Das CSRF-Token oder null
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        // Token aus POST/PUT-Parameter
        $token = $request->getInput('_csrf_token');
        if ($token) {
            return $token;
        }

        // Token aus Header (für AJAX-Requests)
        $token = $request->getHeader('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        // Token aus X-XSRF-TOKEN Header (für AJAX mit Cookies)
        $token = $request->getHeader('X-XSRF-TOKEN');
        if ($token) {
            return $token;
        }

        return null;
    }
}
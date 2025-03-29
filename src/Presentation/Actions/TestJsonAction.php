<?php

declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use Throwable;

#[Injectable]
class TestJsonAction
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Zeigt JSON-Testdaten an
     */
    #[Get('/api/test', 'api.test')]
    public function getData(): ResponseInterface
    {
        // Testdaten erstellen
        $data = [
            'status' => 'success',
            'code' => 200,
            'message' => 'API funktioniert',
            'timestamp' => time(),
            'data' => [
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'session_active' => session_status() === PHP_SESSION_ACTIVE
                ],
                'samples' => [
                    ['id' => 1, 'name' => 'Test 1', 'value' => 100],
                    ['id' => 2, 'name' => 'Test 2', 'value' => 200],
                    ['id' => 3, 'name' => 'Test 3', 'value' => 300]
                ]
            ]
        ];

        return $this->responseFactory->createJson($data);
    }

    /**
     * Verarbeitet POST-Anfragen und gibt die gesendeten Daten zurück
     */
    #[Post('/api/test', 'api.test.post')]
    public function postData(RequestInterface $request): ResponseInterface
    {
        // Debug-Info sammeln
        $inputData = [];

        // 1. JSON-Body extrahieren
        if ($request->isJson()) {
            $jsonBody = $request->getJsonBody();
            if ($jsonBody !== null) {
                $inputData = array_merge($inputData, $jsonBody);
            }
        }

        // 2. POST-Daten hinzufügen
        $postData = $request->getPostData();
        if (!empty($postData)) {
            $inputData = array_merge($inputData, $postData);
        }

        // 3. Query-Parameter hinzufügen
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $inputData = array_merge($inputData, $queryParams);
        }

        // Manuelle Validierung durchführen
        $validationResults = [];
        $isValid = true;

        try {
            // Hole den Validator, wenn verfügbar
            if ($this->container->has(ValidatorInterface::class)) {
                $validator = $this->container->get(ValidatorInterface::class);
                $rules = $this->getValidationRules();

                // Manuell validieren
                $isValid = $validator->validate($inputData, $rules);
                $validationResults = [
                    'success' => $isValid,
                    'rules_applied' => $rules,
                    'errors' => $isValid ? [] : $validator->getErrors()
                ];
            }
        } catch (Throwable $e) {
            $validationResults = [
                'success' => false,
                'exception' => $e->getMessage(),
                'rules_applied' => $this->getValidationRules()
            ];
        }

        // Erstelle Antwortdaten
        $responseData = [
            'status' => $isValid ? 'success' : 'validation_error',
            'message' => $isValid ? 'Daten erfolgreich empfangen' : 'Validierungsfehler',
            'timestamp' => time(),
            'debug' => [
                'input_source' => [
                    'is_json' => $request->isJson(),
                    'content_type' => $request->getContentType(),
                    'json_body' => $request->getJsonBody(),
                    'post_data' => $postData,
                    'query_params' => $queryParams
                ],
                'validation' => $validationResults,
                'combined_data' => $inputData
            ]
        ];

        // Status-Code basierend auf Validierung setzen
        $statusCode = $isValid ? 200 : 422;

        return $this->responseFactory->createJson($responseData, $statusCode);
    }

    /**
     * Gibt Validierungsregeln für den Request zurück
     */
    public function getValidationRules(): array
    {
        return [
            'name' => 'required',
            'email' => 'email',
            'age' => 'numeric'
        ];
    }
}
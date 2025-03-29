<?php


declare(strict_types=1);

namespace App\Presentation\Actions;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Get;
use App\Infrastructure\Routing\Attributes\Post;

#[Injectable]
readonly class RequestDebugAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory
    )
    {
    }

    /**
     * Debug-Endpoint für alle HTTP-Methoden
     */
    #[Post('/debug/request', 'debug.request.post')]
    #[Get('/debug/request', 'debug.request.get')]
    public function debugRequest(RequestInterface $request): ResponseInterface
    {
        // Debug-Informationen sammeln
        $rawBody = $request->getRawBody();
        $headers = $request->getHeaders();
        $contentType = $request->getContentType();
        $isJson = $request->isJson();
        $method = $request->getMethod();

        // JSON-Body-Verarbeitung debuggen
        $jsonBody = null;
        $jsonError = null;

        if ($rawBody) {
            $jsonBody = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
            }
        }

        // Debug-Daten erstellen
        $debugData = [
            'request_info' => [
                'method' => $method,
                'path' => $request->getPath(),
                'query_string' => $request->getQueryString(),
                'content_type' => $contentType,
                'is_json_request' => $isJson,
            ],
            'headers' => $headers,
            'raw_body' => $rawBody,
            'json_processing' => [
                'decoded_body' => $jsonBody,
                'json_error' => $jsonError,
            ],
            'get_params' => $request->getQueryParams(),
            'post_params' => $request->getPostData(),
            'json_body' => $request->getJsonBody(),
        ];

        return $this->responseFactory->createJson($debugData);
    }
}
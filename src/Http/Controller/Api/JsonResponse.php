<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Uniform JSON envelope for the versioned API.
 *
 *   { "ok": true,  "data": { ... }, "error": null }
 *   { "ok": false, "data": null,    "error": { "code": "...", "message": "..." } }
 *
 * The Flutter client parses this single shape, so every endpoint behaves the
 * same way regardless of what it returns.
 */
final class JsonResponse
{
    public const CODE_BAD_REQUEST = 'BAD_REQUEST';
    public const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    public const CODE_NOT_FOUND = 'NOT_FOUND';
    public const CODE_SERVER_ERROR = 'SERVER_ERROR';

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(Response $response, array $data = [], int $status = 200): Response
    {
        return self::write($response, [
            'ok' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    public static function fail(
        Response $response,
        string $code,
        string $message,
        int $status = 400
    ): Response {
        return self::write($response, [
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function write(Response $response, array $payload, int $status): Response
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status)
            ->withBody((new StreamFactory())->createStream($json === false ? '{}' : $json));
    }
}

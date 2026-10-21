<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * CORS for the versioned JSON API.
 *
 * The Flutter **web** build is served from the same origin as the API, so it
 * does not need CORS. The **native** builds (Android/iOS) and a locally
 * hosted development server do, because they are a different origin.
 *
 * Credentials are allowed, so the `Access-Control-Allow-Origin` header must
 * echo the caller's origin — `*` is not permitted with credentials.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private array $allowedMethods = ['GET', 'POST', 'OPTIONS'],
        private string $allowedHeaders = 'Content-Type, Accept'
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');

        if ($request->getMethod() === 'OPTIONS') {
            $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(204);

            return $this->withCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $origin);
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        if ($origin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders)
            ->withHeader('Vary', 'Origin');
    }
}

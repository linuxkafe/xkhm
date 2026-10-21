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
 * ## Why the origin is allow-listed
 *
 * Reflecting the caller's `Origin` back is equivalent to `*` for credentialed
 * requests — it bypasses the only browser rule that protects them. A
 * malicious site could then make credentialed requests to the API and read
 * the responses. The origin is therefore checked against an explicit
 * allowlist and the CORS headers are omitted entirely when it does not match.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $allowedOrigins;

    /**
     * @var list<string>
     */
    private array $allowedMethods;

    private string $allowedHeaders;

    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $allowedMethods
     */
    public function __construct(
        array $allowedOrigins = [],
        array $allowedMethods = ['GET', 'POST', 'OPTIONS'],
        string $allowedHeaders = 'Content-Type, Accept',
        private string $pathPrefix = '/api/'
    ) {
        $this->allowedOrigins = $allowedOrigins !== []
            ? $allowedOrigins
            : self::defaultOrigins();
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }

    /**
     * The origins that are allowed to make credentialed API calls.
     *
     * The web build is same-origin and therefore never appears here. The
     * local development ports are the Flutter web-server defaults.
     *
     * @return list<string>
     */
    public static function defaultOrigins(): array
    {
        $origins = [
            'http://localhost:8080',
            'http://localhost:8081',
            'http://localhost:8090',
            'http://localhost:8091',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:8090',
        ];

        $configured = getenv('XKHM_CORS_ORIGINS');
        if ($configured === false || trim($configured) === '') {
            return $origins;
        }

        $parsed = array_values(array_filter(array_map(
            'trim',
            explode(',', $configured)
        )));

        return $parsed !== [] ? $parsed : $origins;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();

        if (!str_starts_with($path, $this->pathPrefix)) {
            return $handler->handle($request);
        }

        $origin = $request->getHeaderLine('Origin');

        // The preflight is answered here, before the router runs. Registering
        // an OPTIONS route for every API path would make an unknown path
        // answer 405 instead of 404.
        if ($request->getMethod() === 'OPTIONS') {
            $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(204);

            return $this->withCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $origin);
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        if ($origin === '' || !$this->isAllowed($origin)) {
            // Not an allowed origin: no CORS headers at all. The browser
            // blocks the response, which is exactly what we want.
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders)
            ->withHeader('Vary', 'Origin');
    }

    private function isAllowed(string $origin): bool
    {
        foreach ($this->allowedOrigins as $allowed) {
            if (strcasecmp($allowed, $origin) === 0) {
                return true;
            }
        }

        return false;
    }
}

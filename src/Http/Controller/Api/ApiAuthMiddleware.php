<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Guards the `/api/v1` routes: a client session is required unless the route
 * is explicitly public.
 *
 * The session is the *same* PHP session the HTML pages use, so a user logged
 * in on the website is logged in on the Flutter application and vice-versa.
 * That is what keeps the WHMCS authentication semantics in exactly one place.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $session = $request->getAttribute('session');

        if (!is_array($session) || !isset($session['client_id'])) {
            return JsonResponse::fail(
                $request->getAttribute('response') ?? self::newResponse(),
                JsonResponse::CODE_UNAUTHORIZED,
                'Sessão não iniciada. Inicie sessão para continuar.',
                401
            );
        }

        return $handler->handle($request);
    }

    private static function newResponse(): Response
    {
        return (new \Slim\Psr7\Factory\ResponseFactory())->createResponse();
    }
}

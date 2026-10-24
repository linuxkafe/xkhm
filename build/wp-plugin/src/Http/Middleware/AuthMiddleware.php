<?php

declare(strict_types=1);

namespace Xkhm\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $session = $request->getAttribute('session');

        if (!$session || !isset($session['client_id'])) {
            $response = new \Slim\Psr7\Response();
            return $response->withHeader('Location', '/clientarea.php')
                ->withStatus(302);
        }

        // Add client info to request attributes for controllers
        $request = $request->withAttribute('client', $session['client']);

        return $handler->handle($request);
    }
}

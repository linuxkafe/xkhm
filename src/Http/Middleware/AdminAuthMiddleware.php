<?php

declare(strict_types=1);

namespace Xkhm\Http\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!isset($_SESSION['admin_id'])) {
            return $this->responseFactory->createResponse(302)->withHeader('Location', '/admin/login.php');
        }

        return $handler->handle($request);
    }
}

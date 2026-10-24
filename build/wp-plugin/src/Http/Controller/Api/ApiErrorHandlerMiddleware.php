<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Turns an uncaught exception into the JSON envelope when the caller asked
 * for JSON, and into a normal HTML error page otherwise.
 *
 * Without this, a failure inside `/api/v1/*` answers with an HTML stack
 * trace. The Flutter client then reports "Resposta inválida do servidor"
 * instead of the real error — and, with `display_errors` on, the trace leaks
 * absolute paths and SQL to the caller.
 */
final class ApiErrorHandlerMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public function __construct(private bool $displayErrors = false)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            return $this->respond($request, JsonResponse::CODE_NOT_FOUND, 'Recurso não encontrado.', 404);
        } catch (HttpMethodNotAllowedException $e) {
            return $this->respond($request, JsonResponse::CODE_BAD_REQUEST, 'Método não permitido.', 405);
        } catch (\Throwable $e) {
            return $this->respond(
                $request,
                JsonResponse::CODE_SERVER_ERROR,
                $this->displayErrors ? $e->getMessage() : 'Ocorreu um erro ao processar o pedido.',
                500
            );
        }
    }

    /**
     * The handler Slim's error middleware calls for an uncaught exception.
     *
     * This is the *outer* hook: Slim's `ErrorMiddleware` catches the
     * exception before any inner middleware sees it, so the API contract has
     * to be implemented here rather than in a middleware.
     */
    public function __invoke(
        Request $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        $status = 500;
        $code = JsonResponse::CODE_SERVER_ERROR;
        $message = 'Ocorreu um erro ao processar o pedido.';

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $code = JsonResponse::CODE_NOT_FOUND;
            $message = 'Recurso não encontrado.';
        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $code = JsonResponse::CODE_BAD_REQUEST;
            $message = 'Método não permitido.';
        } elseif ($displayErrorDetails) {
            $message = $exception->getMessage();
        }

        if ($this->wantsJson($request)) {
            return JsonResponse::fail(
                (new ResponseFactory())->createResponse(),
                $code,
                $message,
                $status
            );
        }

        $response = (new ResponseFactory())->createResponse($status);
        $response->getBody()->write(
            '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<title>Erro ' . $status . '</title></head><body>'
            . '<h1>Erro ' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
            . '<p><a href="/">Voltar</a></p></body></html>'
        );

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function respond(Request $request, string $code, string $message, int $status): Response
    {
        if (!$this->wantsJson($request)) {
            $response = (new ResponseFactory())->createResponse($status);
            $response->getBody()->write(
                '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
                . '<title>Erro ' . $status . '</title></head><body>'
                . '<h1>Erro ' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
                . '<p><a href="/">Voltar</a></p></body></html>'
            );

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        return JsonResponse::fail(
            (new ResponseFactory())->createResponse(),
            $code,
            $message,
            $status
        );
    }

    private function wantsJson(Request $request): bool
    {
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return true;
        }

        return str_contains($request->getHeaderLine('Accept'), 'application/json');
    }
}

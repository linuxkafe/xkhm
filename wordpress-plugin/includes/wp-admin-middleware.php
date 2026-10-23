<?php
/**
 * Middleware que protege rotas /admin/* exigindo utilizador WordPress
 * com a capability 'manage_options' (administrador).
 *
 * Deve ser adicionado **antes** das rotas de admin no Slim (public/index.php)
 * quando o plugin está activo.
 */
defined('ABSPATH') || exit;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\StreamFactory;

final class WpAdminAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();
        // Aplica apenas a rotas de admin
        if (!str_starts_with($path, '/admin')) {
            return $handler->handle($request);
        }

        // Se o WordPress não estiver carregado, nega acesso
        if (!function_exists('current_user_can')) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write('WordPress não disponível');
            return $response->withStatus(503)->withHeader('Content-Type', 'text/plain');
        }

        // Verifica se há utilizador WP autenticado com capability de admin
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            // Redireciona para wp-login.php com redirect_to para a URL original
            $loginUrl = wp_login_url($request->getUri()->__toString());
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', $loginUrl)
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
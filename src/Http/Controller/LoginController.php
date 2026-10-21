<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\Layout;
use Xkhm\Service\AuthService;

class LoginController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * GET /clientarea.php — Show login form or redirect if logged in
     */
    public function showLogin(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');

        if ($session && isset($session['client_id'])) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $html = $this->renderLoginForm();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /dologin.php — Process login
     */
    public function processLogin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $remember = isset($data['rememberme']);

        if (!$email || !$password) {
            $response->getBody()->write('Utilizador e senha são obrigatórios');
            return $response->withStatus(400);
        }

        $client = $this->authService->verifyPassword($email, $password);

        if (!$client) {
            // Invalid credentials - render the login form again with an error
            $response->getBody()->write($this->renderLoginForm(true));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(401);
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Store client in session
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client'] = $client;

        // Handle remember me
        if ($remember) {
            $token = $this->authService->generateRememberToken();
            // TODO: Store token in database with client ID and expiry
            setcookie('xkhm_remember', $token, [
                'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        // Redirect to client area
        return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
    }

    /**
     * GET /logout.php — Destroy session
     */
    public function logout(Request $request, Response $response): Response
    {
        // Clear session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        // Clear remember me cookie
        setcookie('xkhm_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
    }

    private function renderLoginForm(bool $hasError = false): string
    {
        $error = '';
        if ($hasError) {
            $error = '<div class="alert alert-danger">'
                . 'Utilizador ou senha inválidos. Verifique os dados e tente novamente.'
                . '</div>';
        }

        $body = <<<HTML
            <div class="whmcscontainer">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6 col-md-offset-3">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Iniciar Sessão</h3>
                                </div>
                                <div class="panel-body">
                                    {$error}
                                    <form method="post" action="/dologin.php">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="username" class="form-control" required autofocus>
                                        </div>
                                        <div class="form-group">
                                            <label>Senha</label>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="rememberme"> Lembrar-me
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                                    </form>
                                    <div class="text-center" style="margin-top: 15px;">
                                        <a href="/pwreset.php">Esqueci a senha?</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            HTML;

        return Layout::render('Iniciar Sessão', $body);
    }
}

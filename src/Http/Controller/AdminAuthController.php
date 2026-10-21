<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\Layout;
use Xkhm\Service\AdminAuthService;
use Xkhm\Service\AdminPasswordResetService;

class AdminAuthController
{
    public function __construct(
        private AdminAuthService $authService,
        private ?AdminPasswordResetService $resetService = null
    ) {
    }

    /**
     * GET /admin/login.php
     *
     * With `?action=forgot` it shows the recovery form instead.
     */
    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        $action = (string)($request->getQueryParams()['action'] ?? '');

        if ($action === 'forgot') {
            return $this->renderForgot($request, $response, '');
        }

        $error = $request->getQueryParams()['error'] ?? '';
        $errorHtml = $error
            ? '<div class="alert alert-danger">Utilizador ou senha inválidos.</div>'
            : '';

        $body = <<<HTML
        <div class="container" style="max-width: 400px; margin-top: 50px;">
            <h2>Administração</h2>
            {$errorHtml}
            <form method="post" action="/admin/login.php">
                <div class="form-group">
                    <label>Utilizador</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
            <p><a href="/admin/login.php?action=forgot">Esqueci a senha?</a></p>
            <p><a href="/">Voltar ao início</a></p>
        </div>
        HTML;

        $response->getBody()->write(Layout::render('Administração', $body));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /admin/login.php
     *
     * With `action=forgot` it registers a recovery request instead of
     * authenticating.
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $action = (string)($data['action'] ?? '');

        if ($action === 'forgot') {
            return $this->processForgot($request, $response);
        }

        $admin = $this->authService->verifyCredentials(
            (string)($data['username'] ?? ''),
            (string)($data['password'] ?? '')
        );

        if ($admin) {
            $this->authService->login($admin);

            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        return $response
            ->withHeader('Location', '/admin/login.php?error=1')
            ->withStatus(302);
    }

    /**
     * GET /admin/logout.php
     *
     * Ends the session and sends the administrator back to the login form.
     */
    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();

        return $response
            ->withHeader('Location', '/admin/login.php')
            ->withStatus(302);
    }

    /**
     * The recovery request.
     *
     * The answer is the same whether or not the account exists: revealing
     * which administrator names are registered would be a small but real
     * information leak.
     */
    private function processForgot(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = trim((string)($data['username'] ?? ''));

        if ($username === '' || $this->resetService === null) {
            return $response
                ->withHeader('Location', '/admin/login.php?action=forgot&error=1')
                ->withStatus(302);
        }

        $this->resetService->requestReset($username);

        return $response
            ->withHeader('Location', '/admin/login.php?action=forgot&sent=1')
            ->withStatus(302);
    }

    private function renderForgot(Request $request, Response $response, string $message): Response
    {
        $params = $request->getQueryParams();

        $notice = '';
        if (isset($params['sent'])) {
            $notice = '<div class="alert alert-success">'
                . 'Se a conta existir, enviámos as instruções para redefinir a senha.'
                . '</div>';
        } elseif (isset($params['error'])) {
            $notice = '<div class="alert alert-danger">Indique o utilizador.</div>';
        }

        $body = <<<HTML
        <div class="container" style="max-width: 400px; margin-top: 50px;">
            <h2>Recuperar acesso</h2>
            {$notice}
            <form method="post" action="/admin/login.php">
                <input type="hidden" name="action" value="forgot">
                <div class="form-group">
                    <label>Utilizador</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Pedir recuperação</button>
            </form>
            <p><a href="/admin/login.php">Voltar ao início de sessão</a></p>
        </div>
        HTML;

        $response->getBody()->write(Layout::render('Recuperar acesso', $body));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

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
     * With `?action=forgot` it shows the recovery **request**; with
     * `?action=reset` the form that sets the new password.
     */
    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        $action = (string)($request->getQueryParams()['action'] ?? '');

        if ($action === 'forgot') {
            return $this->renderForgot($request, $response);
        }

        if ($action === 'reset') {
            return $this->renderReset($request, $response);
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
     * With `action=forgot` it registers a recovery request; with
     * `action=reset` it sets the new password with a token.
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $action = (string)($data['action'] ?? '');

        if ($action === 'forgot') {
            return $this->processForgot($request, $response);
        }

        if ($action === 'reset') {
            return $this->processReset($request, $response);
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

    /**
     * Set the new password with a token.
     */
    private function processReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = trim((string)($data['token'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($token === '' || strlen($password) < 8 || $this->resetService === null) {
            return $response
                ->withHeader('Location', '/admin/login.php?action=reset&error=1')
                ->withStatus(302);
        }

        if (!$this->resetService->resetWithToken($token, $password)) {
            return $response
                ->withHeader('Location', '/admin/login.php?action=reset&error=2')
                ->withStatus(302);
        }

        return $response
            ->withHeader('Location', '/admin/login.php?reset=1')
            ->withStatus(302);
    }

    private function renderForgot(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $notice = '';
        if (isset($params['sent'])) {
            $notice = '<div class="alert alert-success">'
                . 'Se a conta existir, enviámos um e-mail com um link para '
                . 'definir a nova senha.'
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

    /**
     * The form that sets the new password.
     *
     * The token arrives in the **fragment** of the URL (`#reset=…`),
     * which the browser never sends to the server: it does not land in
     * the access log. A small script moves it into the field and takes
     * it out of the address bar.
     *
     * The field is **visible** and editable on purpose: without
     * JavaScript — or with a browser that does not run it — the code
     * from the e-mail can still be pasted in.
     */
    private function renderReset(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        // The query string is the fallback, for a link that carries the
        // token that way.
        $token = trim((string)($params['token'] ?? ''));
        $tokenEsc = htmlspecialchars($token, ENT_QUOTES);

        $notice = '';
        if (isset($params['error'])) {
            $error = (string)$params['error'];
            $notice = $error === '2'
                ? '<div class="alert alert-danger">Código inválido ou expirado.</div>'
                : '<div class="alert alert-danger">'
                    . 'Indique o código e uma senha com pelo menos 8 caracteres.'
                    . '</div>';
        } elseif (isset($params['reset'])) {
            $notice = '<div class="alert alert-success">'
                . 'Senha alterada com sucesso. Já pode iniciar sessão.'
                . '</div>';
        }

        $body = <<<HTML
        <div class="container" style="max-width: 400px; margin-top: 50px;">
            <h2>Definir nova senha</h2>
            {$notice}
            <form method="post" action="/admin/login.php" id="lkhm-reset-form">
                <input type="hidden" name="action" value="reset">
                <div class="form-group">
                    <label>Código recebido</label>
                    <input type="text" name="token" id="lkhm-reset-token"
                           class="form-control" value="{$tokenEsc}"
                           autocomplete="one-time-code" required autofocus>
                </div>
                <div class="form-group">
                    <label>Nova senha</label>
                    <input type="password" name="password" class="form-control"
                           autocomplete="new-password" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Definir nova senha</button>
            </form>
            <p><a href="/admin/login.php">Voltar ao início de sessão</a></p>
        </div>
        HTML;

        // The script moves the token from the fragment into the field,
        // and takes it out of the address bar. It is an enhancement:
        // without it, the code from the e-mail is pasted by hand.
        $script = <<<'HTML'
        <script>
        (function () {
          function apply() {
            var field = document.getElementById('lkhm-reset-token');
            if (!field) { return; }
            var hash = window.location.hash || '';
            var token = '';
            hash.replace(/^#/, '').split('&').forEach(function (part) {
              var pair = part.split('=');
              if (pair[0] === 'reset' && pair[1]) {
                token = decodeURIComponent(pair[1]);
              }
            });
            if (token) { field.value = token; }
            if (window.history && window.history.replaceState) {
              window.history.replaceState(
                null, '',
                window.location.pathname + window.location.search
              );
            }
          }
          // The script lives in the head, so the field may not exist yet.
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', apply);
          } else {
            apply();
          }
        })();
        </script>
        HTML;

        $response->getBody()->write(
            Layout::render('Definir nova senha', $body, $script)
        );

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

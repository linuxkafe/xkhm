<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\AdminAuthService;

class AdminAuthController
{
    private AdminAuthService $authService;

    public function __construct(AdminAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * GET /admin/login.php
     */
    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        $error = $request->getQueryParams()['error'] ?? '';
        $errorHtml = $error ? '<div class="alert alert-danger">Login failed. Please try again.</div>' : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Admin Login - LINUXKAFE</title></head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 50px;">
        <h2>Admin Login</h2>
        {$errorHtml}
        <form method="post" action="/admin/login.php">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p><a href="/">Back to homepage</a></p>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /admin/login.php
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $admin = $this->authService->verifyCredentials($data['username'] ?? '', $data['password'] ?? '');

        if ($admin) {
            $this->authService->login($admin);
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        return $response->withHeader('Location', '/admin/login.php?error=1')->withStatus(302);
    }

    /**
     * GET /admin/logout.php
     */
    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();
        return $response->withHeader('Location', '/admin/login.php')->withStatus(302);
    }
}

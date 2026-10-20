<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Http\Controller\LoginController;
use Xkhm\Http\Controller\PasswordResetController;
use Xkhm\Repository\ClientRepositoryPDO;
use Xkhm\Service\AuthService;

require __DIR__ . '/../vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Load configuration
$config = new Config();

// Initialize DB connection (will be lazy-loaded on first use)
// Connection::get($config->getDbConfig());

// Create Slim app
$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Add error middleware (display errors based on config)
$app->addErrorMiddleware(
    $config->getDisplayErrors(),
    true,
    true
);

// Add base path middleware (for subdirectory deployments)
$app->add(function (Request $request, RequestHandler $handler): Response {
    $uri = $request->getUri();
    $basePath = rtrim(dirname($uri->getBasePath()), '/');
    if ($basePath !== '' && $basePath !== '/') {
        $request = $request->withUri($uri->withBasePath($basePath));
    }
    return $handler->handle($request);
});

// Inject session into request
$app->add(function (Request $request, RequestHandler $handler): Response {
    $request = $request->withAttribute('session', $_SESSION);
    return $handler->handle($request);
});

// Health check endpoint
$app->get('/health', function (Request $request, Response $response): Response {
    $response->getBody()->write(json_encode(['status' => 'ok', 'service' => 'xkhm']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Test DB connection endpoint
$app->get('/health/db', function (Request $request, Response $response) use ($config): Response {
    try {
        $pdo = Connection::get($config->getDbConfig());
        $stmt = $pdo->query('SELECT 1');
        $result = $stmt->fetchColumn();
        $data = ['status' => 'ok', 'db' => 'connected', 'result' => $result];
    } catch (\Throwable $e) {
        $data = ['status' => 'error', 'db' => 'disconnected', 'error' => $e->getMessage()];
        $response = $response->withStatus(503);
    }

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// ─── Authenticated Routes ─────────────────────────────────────────────────────

// Client area dashboard
$app->get('/clientarea', function (Request $request, Response $response) {
    $session = $request->getAttribute('session');
    if (!$session || !isset($session['client_id'])) {
        return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
    }
    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Área do Cliente - LINUXKAFE</title></head>';
    $html .= '<body><h1>Bem-vindo, ' . htmlspecialchars($session['client']['firstname'] ?? 'Cliente') . '</h1>';
    $html .= '<p><a href="/logout.php">Logout</a></p></body></html>';
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// ─── Authentication Routes ────────────────────────────────────────────────────

// Repository and service instances
$clientRepo = new ClientRepositoryPDO();
$authService = new AuthService($clientRepo);
$loginController = new LoginController($authService);
$pwResetController = new PasswordResetController($clientRepo);

// Login page
$app->get('/clientarea.php', [$loginController, 'showLogin']);

// Login processor
$app->post('/dologin.php', [$loginController, 'processLogin']);

// Logout
$app->get('/logout.php', [$loginController, 'logout']);

// Password reset form
$app->get('/pwreset.php', [$pwResetController, 'showForm']);

// Password reset processor
$app->post('/pwreset.php', [$pwResetController, 'process']);

// ─── Public Routes ─────────────────────────────────────────────────────────────

// Cart routes
$app->group('/cart', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Cart - TODO: implement');
        return $response;
    });
});

// Domain checker
$app->group('/domainchecker', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Domain Checker - TODO: implement');
        return $response;
    });
});

// Knowledgebase
$app->group('/knowledgebase', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Knowledgebase - TODO: implement');
        return $response;
    });
});

// Support tickets
$app->group('/supporttickets', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Support Tickets - TODO: implement');
        return $response;
    });
});

// Affiliates
$app->group('/affiliates', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Affiliates - TODO: implement');
        return $response;
    });
});

// Announcements
$app->get('/announcements', function (Request $request, Response $response): Response {
    $response->getBody()->write('Announcements - TODO: implement');
    return $response;
});

// Server status
$app->get('/serverstatus', function (Request $request, Response $response): Response {
    $response->getBody()->write('Server Status - TODO: implement');
    return $response;
});

// API routes (WHMCS compatible)
$app->group('/api', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('WHMCS API - TODO: implement');
        return $response;
    });
});

// Run the app
$app->run();

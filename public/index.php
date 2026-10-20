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

// Auth routes
$app->get('/clientarea.php', [$loginController, 'showLogin']);
$app->post('/dologin.php', [$loginController, 'processLogin']);
$app->get('/logout.php', [$loginController, 'logout']);
$app->get('/pwreset.php', [$pwResetController, 'showForm']);
$app->post('/pwreset.php', [$pwResetController, 'process']);

// ─── Client Area ───────────────────────────────────────────────────────────────

// Invoice service setup
$invoiceRepo = new \Xkhm\Repository\InvoiceRepositoryPDO();
$orderRepo = new \Xkhm\Repository\OrderRepositoryPDO();
$billingService = new \Xkhm\Service\BillingService($invoiceRepo, $orderRepo, $clientRepo);
$gatewayService = new \Xkhm\Service\GatewayService($invoiceRepo);
$invoiceController = new InvoiceController($billingService, $gatewayService);
$orderController = new OrderController($billingService);

// Domain service setup
$domainRepo = new \Xkhm\Repository\DomainRepositoryPDO();
$domainService = new \Xkhm\Service\DomainService($domainRepo);
$domainController = new DomainController($domainService);

// Ticket service setup
$ticketRepo = new \Xkhm\Repository\TicketRepositoryPDO();
$ticketService = new \Xkhm\Service\TicketService($ticketRepo);
$ticketController = new TicketController($ticketService);

// Knowledgebase service setup
$kbRepo = new \Xkhm\Repository\KnowledgebaseRepositoryPDO();
$kbService = new \Xkhm\Service\KnowledgebaseService($kbRepo);
$kbController = new KnowledgebaseController($kbService);

// Announcement service setup
$announceRepo = new \Xkhm\Repository\AnnouncementRepositoryPDO();
$announceService = new \Xkhm\Service\AnnouncementService($announceRepo);
$announceController = new AnnouncementController($announceService);

// Affiliate service setup
$affiliateRepo = new \Xkhm\Repository\AffiliateRepositoryPDO();
$affiliateService = new \Xkhm\Service\AffiliateService($affiliateRepo);
$affiliateController = new AffiliateController($affiliateService);

// Admin auth service setup
$adminAuthService = new \Xkhm\Service\AdminAuthService(new \Xkhm\Repository\AdminRepositoryPDO());
$adminAuthController = new AdminAuthController($adminAuthService);

// Create admin auth middleware
$adminAuthMiddleware = new AdminAuthMiddleware($app->getResponseFactory());

// Client area dashboard
$app->get('/clientarea', function (Request $request, Response $response) use ($billingService) {
    $session = $request->getAttribute('session');
    if (!$session || !isset($session['client_id'])) {
        return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
    }
    $clientId = $session['client_id'];
    $summary = $billingService->getInvoiceSummary($clientId);
    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Área do Cliente - LINUXKAFE</title></head>';
    $html .= '<body><div class="container"><h1>Bemvindo, ' . htmlspecialchars($session['client']['firstname'] ?? 'Cliente') . '</h1>';
    $html .= '<h3>Resumo Financeiro</h3>';
    $html .= '<p>Crédito: $' . number_format($summary['credit_balance'], 2) . '</p>';
    $html .= '<p>Total em Aberto: $' . number_format($summary['unpaid_total'], 2) . '</p>';
    $html .= '<p><a href="/clientarea.php?action=invoices">Ver Faturas</a> | ';
    $html .= '<a href="/clientarea.php?action=orders">Ver Pedidos</a> | ';
    $html .= '<a href="/logout.php">Logout</a></p>';
    $html .= '</div></body></html>';
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// Invoice controller routes
$app->get('/viewinvoice.php', [$invoiceController, 'view']);
$app->get('/clientarea.php', function (Request $request, Response $response) use ($invoiceController, $orderController) {
    $queryParams = $request->getQueryParams();
    $action = $queryParams['action'] ?? '';

    // Redirect to login if not authenticated
    $session = $request->getAttribute('session');
    if (!$session || !isset($session['client_id'])) {
        return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
    }

    // Handle invoice listing
    if ($action === 'invoices') {
        return $invoiceController->list($request, $response);
    }

    // Handle order listing
    if ($action === 'orders') {
        return $orderController->list($request, $response);
    }

    // Default: show dashboard
    // This is handled by /clientarea route above
    return $response->withHeader('Location', '/clientarea')->withStatus(302);
});

// ─── Public Routes ─────────────────────────────────────────────────────────────

// Cart routes
$app->group('/cart', function ($group) {
    $group->get('', function (Request $request, Response $response): Response {
        $response->getBody()->write('Cart - TODO: implement');
        return $response;
    });
});

// Domain checker
$app->get('/domainchecker.php', [$domainController, 'checker']);

// Domain management
$app->get('/domain.php', [$domainController, 'list']);
$app->post('/domain.php', [$domainController, 'register']);

// Knowledgebase
$app->get('/knowledgebase.php', function (Request $request, Response $response) use ($kbController) {
    $queryParams = $request->getQueryParams();
    $action = $queryParams['action'] ?? 'search';
    $search = $queryParams['search'] ?? '';
    $articleId = (int)($queryParams['id'] ?? 0);

    // If viewing article or searching
    if ($action === 'article' || ($action === 'search' && strlen($search) >= 2)) {
        return $kbController->index($request, $response);
    }

    // If browsing categories
    if (isset($queryParams['category'])) {
        return $kbController->index($request, $response);
    }

    return $kbController->index($request, $response);
});

// Support tickets
$app->group('/supporttickets', function ($group) use ($ticketController) {
    $group->get('', [$ticketController, 'list']);
    $group->get('/new', [$ticketController, 'new']);
    $group->post('', [$ticketController, 'create']);
    $group->post('/reply', [$ticketController, 'reply']);
});
$app->get('/supporttickets.php', [$ticketController, 'list']);
$app->get('/supporttickets.php', function (Request $request, Response $response) use ($ticketController) {
    $queryParams = $request->getQueryParams();
    $do = $queryParams['do'] ?? '';
    if ($do === 'new') {
        return $ticketController->new($request, $response);
    }
    if ($do === 'ticket') {
        return $ticketController->view($request, $response);
    }
    return $ticketController->list($request, $response);
});
$app->post('/supporttickets.php', [$ticketController, 'create']);

// Affiliates
$app->get('/affiliates.php', [$affiliateController, 'index']);
$app->post('/affiliates.php', [$affiliateController, 'signup']);

// ─── Admin Routes ─────────────────────────────────────────────────────────────

$app->group('/admin', function ($group) use ($app) {
    // Auth routes (no middleware)
    $group->get('/login.php', [$adminAuthController, 'showLogin']);
    $group->post('/login.php', [$adminAuthController, 'login']);
    $group->get('/logout.php', [$adminAuthController, 'logout']);

    // Protected routes
    $group->get('', function (Request $request, Response $response) {
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Admin Panel - LINUXKAFE</title></head>';
        $html .= '<body><div class="container"><h1>Admin Panel</h1>';
        $html .= '<p><a href="/admin/clients.php">Gerenciar Clientes</a> | ';
        $html .= '<a href="/admin/invoices.php">Gerenciar Faturas</a> | ';
        $html .= '<a href="/admin/orders.php">Gerenciar Pedidos</a></p>';
        $html .= '<p><a href="/admin/logout.php">Logout</a></p>';
        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
})->add($adminAuthMiddleware);

// Announcements
$app->get('/announcements.php', [$announceController, 'index']);

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

<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Http\Controller\LoginController;
use Xkhm\Http\Controller\PasswordResetController;
use Xkhm\Http\Controller\InvoiceController;
use Xkhm\Http\Controller\OrderController;
use Xkhm\Http\Controller\DomainController;
use Xkhm\Http\Controller\TicketController;
use Xkhm\Http\Controller\KnowledgebaseController;
use Xkhm\Http\Controller\AnnouncementController;
use Xkhm\Http\Controller\AffiliateController;
use Xkhm\Http\Controller\AdminAuthController;
use Xkhm\Http\Middleware\AdminAuthMiddleware;
use Xkhm\Http\Middleware\StylesheetMiddleware;
use Xkhm\Http\View\Layout;
use Xkhm\Http\Controller\ServerStatusController;
use Xkhm\Http\Controller\SslController;
use Xkhm\Http\Controller\ApiController;
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

// Create Slim app
$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Add error middleware (display errors based on config)
$app->addErrorMiddleware(
    true, // Always show errors for debugging
    true,
    true
);

// Inject session into request
$app->add(function (Request $request, RequestHandler $handler): Response {
    $session = $_SESSION ?? [];
    $request = $request->withAttribute('session', $session);
    return $handler->handle($request);
});

// Guarantee no HTML page ships without the LINUXKAFE stylesheets
$app->add(new StylesheetMiddleware());

// Parse JSON request bodies (the Flutter client posts JSON).
// Without this, getParsedBody() is null for application/json requests.
$app->addBodyParsingMiddleware();

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

// ─── Repository and Service Setup ─────────────────────────────────────────────

$clientRepo = new ClientRepositoryPDO();
$authService = new AuthService($clientRepo);
$loginController = new LoginController($authService);
$pwResetController = new PasswordResetController($clientRepo);

$invoiceRepo = new \Xkhm\Repository\InvoiceRepositoryPDO();
$orderRepo = new \Xkhm\Repository\OrderRepositoryPDO();
$billingService = new \Xkhm\Service\BillingService($invoiceRepo, $orderRepo, $clientRepo);
$gatewayService = new \Xkhm\Service\GatewayService($invoiceRepo);
$invoiceController = new InvoiceController($billingService, $gatewayService);
$orderController = new OrderController($billingService);

$domainRepo = new \Xkhm\Repository\DomainRepositoryPDO();
$domainService = new \Xkhm\Service\DomainService($domainRepo);
$domainController = new DomainController($domainService);

$ticketRepo = new \Xkhm\Repository\TicketRepositoryPDO();
$ticketService = new \Xkhm\Service\TicketService($ticketRepo);
$ticketController = new TicketController($ticketService);

$kbRepo = new \Xkhm\Repository\KnowledgebaseRepositoryPDO();
$kbService = new \Xkhm\Service\KnowledgebaseService($kbRepo);
$kbController = new KnowledgebaseController($kbService);

$announceRepo = new \Xkhm\Repository\AnnouncementRepositoryPDO();
$announceService = new \Xkhm\Service\AnnouncementService($announceRepo);
$announceController = new AnnouncementController($announceService);

$affiliateRepo = new \Xkhm\Repository\AffiliateRepositoryPDO();
$affiliateService = new \Xkhm\Service\AffiliateService($affiliateRepo);
$affiliateController = new AffiliateController($affiliateService);

$adminAuthService = new \Xkhm\Service\AdminAuthService(new \Xkhm\Repository\AdminRepositoryPDO());
$adminAuthController = new AdminAuthController($adminAuthService);

$serverStatusService = new \Xkhm\Service\ServerStatusService();
$serverStatusController = new ServerStatusController($serverStatusService);

$sslService = new \Xkhm\Service\SslService();
$sslController = new SslController($sslService);

$apiController = new ApiController($billingService, $ticketService, $domainService);

$adminAuthMiddleware = new AdminAuthMiddleware($app->getResponseFactory());

// ─── Versioned JSON API (/api/v1) — consumed by the Flutter application ─────

$apiV1Controller = new \Xkhm\Http\Controller\Api\ApiV1Controller(
    $authService,
    $billingService,
    $ticketService,
    $domainService,
    $kbService,
    $announceService,
    $serverStatusService
);

$apiAuth = new \Xkhm\Http\Controller\Api\ApiAuthMiddleware();
$apiCors = new \Xkhm\Http\Controller\Api\CorsMiddleware();

// All /api/v1 routes live in one group so the CORS middleware covers them
// uniformly (the native builds and a local dev server are a different
// origin; the web build is served next to the API and does not need it).
$app->group('/api/v1', function ($group) use ($apiV1Controller, $apiAuth) {
    // Public endpoints
    $group->get('/health', [$apiV1Controller, 'health']);
    $group->post('/auth/login', [$apiV1Controller, 'login']);
    $group->get('/knowledgebase/categories', [$apiV1Controller, 'knowledgebaseCategories']);
    $group->get('/knowledgebase', [$apiV1Controller, 'knowledgebase']);
    $group->get('/knowledgebase/{id:[0-9]+}', [$apiV1Controller, 'knowledgebaseArticle']);
    $group->get('/announcements', [$apiV1Controller, 'announcements']);
    $group->get('/serverstatus', [$apiV1Controller, 'serverStatus']);
    $group->get('/domains/pricing', [$apiV1Controller, 'domainPricing']);
    $group->get('/domains/check', [$apiV1Controller, 'domainCheck']);

    // Authenticated endpoints
    $group->get('/auth/session', [$apiV1Controller, 'session'])->add($apiAuth);
    $group->post('/auth/logout', [$apiV1Controller, 'logout'])->add($apiAuth);
    $group->get('/client/summary', [$apiV1Controller, 'clientSummary'])->add($apiAuth);
    $group->get('/client/invoices', [$apiV1Controller, 'clientInvoices'])->add($apiAuth);
    $group->get('/client/invoices/{id:[0-9]+}', [$apiV1Controller, 'clientInvoice'])->add($apiAuth);
    $group->get('/client/domains', [$apiV1Controller, 'clientDomains'])->add($apiAuth);
    $group->get('/client/tickets', [$apiV1Controller, 'clientTickets'])->add($apiAuth);
    $group->get('/client/tickets/{id:[0-9]+}', [$apiV1Controller, 'clientTicket'])->add($apiAuth);
    $group->post('/client/tickets', [$apiV1Controller, 'createTicket'])->add($apiAuth);
})->add($apiCors);

// CORS preflight for every /api/v1 path
$app->options('/api/v1/{path:.*}', fn (Request $request, Response $response): Response => $response)
    ->add($apiCors);

// ─── Authentication Routes ────────────────────────────────────────────────────

$app->get('/clientarea.php', function (Request $request, Response $response) use ($loginController, $invoiceController, $orderController, $billingService) {
    $queryParams = $request->getQueryParams();
    $action = $queryParams['action'] ?? '';

    // Show login form if not authenticated
    $session = $request->getAttribute('session');
    if (!$session || !isset($session['client_id'])) {
        return $loginController->showLogin($request, $response);
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
    $clientId = $session['client_id'];
    $summary = $billingService->getInvoiceSummary($clientId);
    $html = '<h1>Bemvindo, ' . htmlspecialchars($session['client']['firstname'] ?? 'Cliente') . '</h1>';
    $html .= '<h3>Resumo Financeiro</h3>';
    $html .= '<p>Crédito: $' . number_format($summary['credit_balance'], 2) . '</p>';
    $html .= '<p>Total em Aberto: $' . number_format($summary['unpaid_total'], 2) . '</p>';
    $html .= '<p><a href="/clientarea.php?action=invoices">Ver Faturas</a> | ';
    $html .= '<a href="/clientarea.php?action=orders">Ver Pedidos</a> | ';
    $html .= '<a href="/logout.php">Logout</a></p>';
    $response->getBody()->write(Layout::render('Área do Cliente', $html));
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});
$app->post('/dologin.php', [$loginController, 'processLogin']);
$app->get('/logout.php', [$loginController, 'logout']);
$app->get('/pwreset.php', [$pwResetController, 'showForm']);
$app->post('/pwreset.php', [$pwResetController, 'process']);

// ─── Public Routes ─────────────────────────────────────────────────────────────

// Domain checker
$app->get('/domainchecker.php', [$domainController, 'checker']);

// Domain management
$app->get('/domain.php', [$invoiceController, 'view']);

// Knowledgebase
$app->get('/knowledgebase.php', function (Request $request, Response $response) use ($kbController) {
    return $kbController->index($request, $response);
});

// Support tickets
$app->get('/supporttickets.php', [$ticketController, 'list']);
$app->post('/supporttickets.php', [$ticketController, 'create']);

// SSL Configuration
$app->get('/ssl.php', [$sslController, 'index']);

// Server Status (public)
$app->get('/serverstatus.php', [$serverStatusController, 'index']);

// Announcements
$app->get('/announcements.php', [$announceController, 'index']);

// API
$app->get('/api.php', [$apiController, 'handleApi']);

// Affiliates
$app->get('/affiliates.php', [$affiliateController, 'index']);

// ─── Admin Routes ─────────────────────────────────────────────────────────────

// Admin auth routes (no middleware)
$app->get('/admin/login.php', [$adminAuthController, 'showLogin']);
$app->post('/admin/login.php', [$adminAuthController, 'login']);
$app->get('/admin/logout.php', [$adminAuthController, 'logout']);
$app->get('/admin', function (Request $request, Response $response) {
    $html = '<h1>Admin Panel</h1>';
    $html .= '<p><a href="/admin/logout.php">Terminar sessão</a></p>';
    $response->getBody()->write(Layout::render('Admin Panel', $html));
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
})->add($adminAuthMiddleware);

// Redirect /admin/ to /admin
$app->get('/admin/', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/admin')->withStatus(302);
});

// Default route - show welcome page
$app->get('/', function (Request $request, Response $response) {
    $html = '<h1>Bemvindo ao LINUXKAFE</h1>';
    $html .= '<p>Service Provider - Hosting, Domains, SSL</p>';
    $html .= '<ul>';
    $html .= '<li><a href="/clientarea.php">Área do Cliente</a></li>';
    $html .= '<li><a href="/domainchecker.php">Verificar Domínio</a></li>';
    $html .= '<li><a href="/announcements.php">Anúncios</a></li>';
    $html .= '<li><a href="/knowledgebase.php">Base de Conhecimento</a></li>';
    $html .= '<li><a href="/supporttickets.php">Abrir Ticket</a></li>';
    $html .= '<li><a href="/serverstatus.php">Status do Servidor</a></li>';
    $html .= '</ul>';
    $response->getBody()->write(Layout::render('Início', $html));
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// Run the app
$app->run();
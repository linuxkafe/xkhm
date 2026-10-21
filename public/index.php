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

// Add error middleware. The detail level follows the configuration, so a
// production deployment never leaks stack traces to an anonymous caller.
$errorMiddleware = $app->addErrorMiddleware(
    $config->getDisplayErrors(),
    true,
    true
);

// API failures must answer with the JSON envelope, never an HTML stack trace.
// Slim's error middleware catches the exception before any inner middleware
// sees it, so the contract is implemented as its error handler.
$errorMiddleware->setDefaultErrorHandler(
    new \Xkhm\Http\Controller\Api\ApiErrorHandlerMiddleware($config->getDisplayErrors())
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
$sslController = new SslController($sslService, $domainService);

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
    $serverStatusService,
    $sslService,
    $affiliateService,
    $orderRepo,
    new \Xkhm\Service\PasswordResetService($clientRepo)
);

$apiAuth = new \Xkhm\Http\Controller\Api\ApiAuthMiddleware();

// All /api/v1 routes live in one group so the CORS middleware covers them
// uniformly (the native builds and a local dev server are a different
// origin; the web build is served next to the API and does not need it).
$app->group('/api/v1', function ($group) use ($apiV1Controller, $apiAuth) {
    // Public endpoints
    $group->get('/health', [$apiV1Controller, 'health']);
    $group->post('/auth/login', [$apiV1Controller, 'login']);
    // The password reset is public by definition: it is for someone who
    // cannot log in.
    $group->post('/auth/forgot-password', [$apiV1Controller, 'forgotPassword']);
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
    $group->get('/home', [$apiV1Controller, 'home'])->add($apiAuth);
    $group->get('/client/summary', [$apiV1Controller, 'clientSummary'])->add($apiAuth);
    $group->get('/client/invoices', [$apiV1Controller, 'clientInvoices'])->add($apiAuth);
    $group->get('/client/invoices/{id:[0-9]+}', [$apiV1Controller, 'clientInvoice'])->add($apiAuth);
    $group->get('/client/orders', [$apiV1Controller, 'clientOrders'])->add($apiAuth);
    $group->get('/client/domains', [$apiV1Controller, 'clientDomains'])->add($apiAuth);
    $group->get('/client/ssl', [$apiV1Controller, 'clientSsl'])->add($apiAuth);
    $group->get('/client/affiliate', [$apiV1Controller, 'clientAffiliate'])->add($apiAuth);
    $group->get('/client/tickets', [$apiV1Controller, 'clientTickets'])->add($apiAuth);
    $group->get('/client/tickets/{id:[0-9]+}', [$apiV1Controller, 'clientTicket'])->add($apiAuth);
    $group->post('/client/tickets', [$apiV1Controller, 'createTicket'])->add($apiAuth);
});

// ─── Retired client-facing pages ─────────────────────────────────────────────
//
// The client area migrated to Flutter (T025). The HTML routes are kept as
// redirects so that no bookmark breaks, and the handlers are NOT registered
// — the pages are unreachable. Re-enabling one is a single line.
//
// The PHP stays: it is the API server (`/api/v1`, `/api.php`), the admin
// panel, and the health endpoints.

$retiredRoutes = [
    '/clientarea.php',
    '/dologin.php',
    '/logout.php',
    '/pwreset.php',
    '/domainchecker.php',
    '/domain.php',
    '/viewinvoice.php',
    '/knowledgebase.php',
    '/supporttickets.php',
    '/ssl.php',
    '/serverstatus.php',
    '/announcements.php',
    '/affiliates.php',
];

foreach ($retiredRoutes as $retired) {
    $app->any($retired, function (Request $request, Response $response) {
        return $response->withHeader('Location', '/')->withStatus(302);
    });
}

// ─── API ──────────────────────────────────────────────────────────────────────

// The WHMCS-compatible API. External integrations use it; it is not a
// front-end and it stays.
$app->get('/api.php', [$apiController, 'handleApi']);

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

// ─── The Flutter application ──────────────────────────────────────────────────
//
// The client area is the Flutter web build. It is mounted by Docker at
// `public/app/` (see docker-compose.yml) and its `<base href="/app/">` makes
// the engine load its assets from there.
//
// Serving `index.html` at `/` means the client lands on the application
// directly, with no redirect and no second URL.

$flutterIndex = __DIR__ . '/app/index.html';

if (is_file($flutterIndex)) {
    $app->get('/', function (Request $request, Response $response) use ($flutterIndex) {
        $html = (string) file_get_contents($flutterIndex);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream($html));
    });
} else {
    // The build is missing — say so plainly instead of serving a blank page.
    $app->get('/', function (Request $request, Response $response) {
        $body = '<h1>A aplicação não está instalada</h1>'
            . '<p>Corra <code>make flutter-build</code> para gerar a aplicação '
            . 'Flutter e reinicie o contentor.</p>';

        $response->getBody()->write(Layout::render('Aplicação indisponível', $body));

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(503);
    });
}

// CORS must be the outermost middleware so it runs before the router.
// It is registered here (after every route) to keep the execution order
// explicit: Slim runs the middleware in reverse order of registration.
$app->add(new \Xkhm\Http\Controller\Api\CorsMiddleware());

// Run the app
$app->run();
<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\BillingService;
use Xkhm\Service\TicketService;
use Xkhm\Service\DomainService;
use Xkhm\Service\AffiliateService;

class ApiController
{
    private BillingService $billingService;
    private TicketService $ticketService;
    private DomainService $domainService;

    public function __construct(
        BillingService $billingService,
        TicketService $ticketService,
        DomainService $domainService
    ) {
        $this->billingService = $billingService;
        $this->ticketService = $ticketService;
        $this->domainService = $domainService;
    }

    /**
     * GET /api.php?action=getclientinfo&clientid=1
     */
    public function getClientInfo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $clientId = (int)($queryParams['clientid'] ?? 0);

        if (!$clientId) {
            return $this->errorResponse($response, 'clientid required');
        }

        // The client id is taken from the *session*, never from the query
        // string: an anonymous caller must not be able to read another
        // client's record.
        $session = $request->getAttribute('session');
        if (!is_array($session) || !isset($session['client_id'])) {
            return $this->errorResponse($response, 'Authentication required', 401);
        }

        $client = $session['client'] ?? [];

        return $this->jsonResponse($response, [
            'id' => (int)$session['client_id'],
            'firstname' => $client['firstname'] ?? '',
            'lastname' => $client['lastname'] ?? '',
            'email' => $client['email'] ?? '',
            'status' => $client['status'] ?? 'active',
        ]);
    }

    /**
     * GET /api.php?action=getinvoiceinfo&invid=1
     */
    public function getInvoiceInfo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $invoiceId = (int)($queryParams['invid'] ?? 0);

        if (!$invoiceId) {
            return $this->errorResponse($response, 'invid required');
        }

        $session = $request->getAttribute('session');
        if (!is_array($session) || !isset($session['client_id'])) {
            return $this->errorResponse($response, 'Authentication required', 401);
        }

        $invoice = $this->billingService->getInvoiceWithItems($invoiceId);
        if (!$invoice) {
            return $this->errorResponse($response, 'Invoice not found', 404);
        }

        // Ownership check: a client may only read their own invoices.
        if ((int)($invoice['userid'] ?? 0) !== (int)$session['client_id']) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }

        return $this->jsonResponse($response, $invoice);
    }

    /**
     * GET /api.php?action=getticketinfo&tid=1
     */
    public function getTicketInfo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $ticketId = (int)($queryParams['tid'] ?? 0);

        if (!$ticketId) {
            return $this->errorResponse($response, 'tid required');
        }

        $session = $request->getAttribute('session');
        if (!is_array($session) || !isset($session['client_id'])) {
            return $this->errorResponse($response, 'Authentication required', 401);
        }

        $ticket = $this->ticketService->getTicketWithReplies($ticketId);
        if (!$ticket) {
            return $this->errorResponse($response, 'Ticket not found', 404);
        }

        // Ownership check: a client may only read their own tickets.
        if ((int)($ticket['userid'] ?? 0) !== (int)$session['client_id']) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }

        return $this->jsonResponse($response, $ticket);
    }

    /**
     * GET /api.php?action=getdomaininfo&domain=example.com
     *
     * Supports both session authentication (client area) and WHMCS API
     * authentication (username/password for external integrations like
     * the WordPress bridge).
     */
    public function getDomainInfo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $domain = $queryParams['domain'] ?? '';

        if (!$domain) {
            return $this->errorResponse($response, 'domain required');
        }

        // Check for WHMCS API authentication (username + password)
        $apiUser = $queryParams['username'] ?? '';
        $apiPass = $queryParams['password'] ?? '';
        if ($apiUser !== '' && $apiPass !== '') {
            if (!$this->validateApiCredentials($apiUser, $apiPass)) {
                return $this->errorResponse($response, 'Authentication failed', 401);
            }
        } else {
            // Fall back to session authentication
            $session = $request->getAttribute('session');
            if (!is_array($session) || !isset($session['client_id'])) {
                return $this->errorResponse($response, 'Authentication required', 401);
            }
        }

        $available = !$this->domainService->isRegistered($domain);

        return $this->jsonResponse($response, [
            'domain' => $domain,
            'available' => $available,
        ]);
    }

    /**
     * Validate WHMCS API credentials.
     *
     * The credentials are stored in tblconfiguration as APIUsername and
     * APIPassword (md5 hash).
     */
    private function validateApiCredentials(string $username, string $password): bool
    {
        $config = new \Xkhm\Config();
        $pdo = \Xkhm\Database\Connection::get($config->getDbConfig());

        $stmt = $pdo->prepare('SELECT value FROM tblconfiguration WHERE setting = ?');
        $stmt->execute(['APIUsername']);
        $storedUser = $stmt->fetchColumn();

        $stmt->execute(['APIPassword']);
        $storedPassHash = $stmt->fetchColumn();

        if ($storedUser === false || $storedPassHash === false) {
            return false;
        }

        // WHMCS stores the API password as md5
        return $username === $storedUser && md5($password) === $storedPassHash;
    }

    /**
     * POST /api.php — General endpoint for API calls
     */
    public function handleApi(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $action = $queryParams['action'] ?? '';

        // NOTE: the match arms must be *returned*. Discarding them would drop
        // the Content-Type header set by jsonResponse()/errorResponse().
        return match ($action) {
            'getclientinfo' => $this->getClientInfo($request, $response),
            'getinvoiceinfo' => $this->getInvoiceInfo($request, $response),
            'getticketinfo' => $this->getTicketInfo($request, $response),
            'getdomaininfo' => $this->getDomainInfo($request, $response),
            default => $this->errorResponse($response, 'Unknown action: ' . $action),
        };
    }

    private function jsonResponse(Response $response, array $data): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $data = ['result' => 'error', 'message' => $message];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

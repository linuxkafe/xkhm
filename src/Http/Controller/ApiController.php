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

        // Would need ClientRepository for full implementation
        $data = ['id' => $clientId, 'status' => 'active'];

        return $this->jsonResponse($response, $data);
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

        $invoice = $this->billingService->getInvoiceWithItems($invoiceId);
        if (!$invoice) {
            return $this->errorResponse($response, 'Invoice not found');
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

        $ticket = $this->ticketService->getTicketWithReplies($ticketId);
        if (!$ticket) {
            return $this->errorResponse($response, 'Ticket not found');
        }

        return $this->jsonResponse($response, $ticket);
    }

    /**
     * GET /api.php?action=getdomaininfo&domain=example.com
     */
    public function getDomainInfo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $domain = $queryParams['domain'] ?? '';

        if (!$domain) {
            return $this->errorResponse($response, 'domain required');
        }

        $available = !$this->domainService->isRegistered($domain);

        return $this->jsonResponse($response, [
            'domain' => $domain,
            'available' => $available,
        ]);
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

    private function errorResponse(Response $response, string $message): Response
    {
        $data = ['result' => 'error', 'message' => $message];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
}

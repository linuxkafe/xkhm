<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Repository\OrderRepositoryInterface;
use Xkhm\Service\AffiliateService;
use Xkhm\Service\AnnouncementService;
use Xkhm\Service\AuthService;
use Xkhm\Service\BillingService;
use Xkhm\Service\DomainService;
use Xkhm\Service\KnowledgebaseService;
use Xkhm\Service\PasswordResetService;
use Xkhm\Service\ServerStatusService;
use Xkhm\Service\SslService;
use Xkhm\Service\TicketService;

/**
 * The versioned JSON API consumed by the Flutter application.
 *
 * This controller is a *serialisation layer only*. It reuses the existing
 * services and repositories verbatim — there is no second implementation of
 * the WHMCS business rules, and no SQL of its own. That is what keeps the
 * service compatible with the existing `linuxkonect` database.
 */
final class ApiV1Controller
{
    public function __construct(
        private AuthService $authService,
        private BillingService $billingService,
        private TicketService $ticketService,
        private DomainService $domainService,
        private KnowledgebaseService $knowledgebaseService,
        private AnnouncementService $announcementService,
        private ServerStatusService $serverStatusService,
        private SslService $sslService,
        private AffiliateService $affiliateService,
        private OrderRepositoryInterface $orderRepository,
        private PasswordResetService $passwordResetService
    ) {
    }

    // ─── Public endpoints ────────────────────────────────────────────────────

    /**
     * GET /api/v1/health
     */
    public function health(Request $request, Response $response): Response
    {
        return JsonResponse::ok($response, [
            'status' => 'ok',
            'service' => 'xkhm',
            'api' => 'v1',
        ]);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = (string)($data['email'] ?? $data['username'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Email e senha são obrigatórios.',
                400
            );
        }

        $client = $this->authService->verifyPassword($email, $password);

        if ($client === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_UNAUTHORIZED,
                'Utilizador ou senha inválidos.',
                401
            );
        }

        session_regenerate_id(true);
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client'] = $client;

        return JsonResponse::ok($response, [
            'client' => [
                'id' => (int)$client['id'],
                'firstname' => $client['firstname'] ?? '',
                'lastname' => $client['lastname'] ?? '',
                'email' => $client['email'] ?? '',
                'status' => $client['status'] ?? '',
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/session
     */
    public function session(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $client = $session['client'] ?? [];

        return JsonResponse::ok($response, [
            'client' => [
                'id' => (int)($session['client_id'] ?? 0),
                'firstname' => $client['firstname'] ?? '',
                'lastname' => $client['lastname'] ?? '',
                'email' => $client['email'] ?? '',
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['client_id'], $_SESSION['client']);

        return JsonResponse::ok($response, ['loggedOut' => true]);
    }

    /**
     * GET /api/v1/knowledgebase/categories
     */
    public function knowledgebaseCategories(Request $request, Response $response): Response
    {
        $categories = $this->knowledgebaseService->getCategories();
        $counts = $this->knowledgebaseService->getArticleCounts();

        $items = [];
        foreach ($categories as $category) {
            $id = (int)$category['id'];
            $items[] = [
                'id' => $id,
                'title' => $category['title'] ?? '',
                'description' => $category['description'] ?? '',
                'sortOrder' => (int)($category['sortorder'] ?? 0),
                'articleCount' => (int)($counts[$id]['count'] ?? 0),
            ];
        }

        return JsonResponse::ok($response, ['categories' => $items]);
    }

    /**
     * GET /api/v1/knowledgebase?category=&search=
     */
    public function knowledgebase(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = trim((string)($params['search'] ?? ''));
        $categoryId = (int)($params['category'] ?? 0);

        if ($search !== '') {
            $articles = $this->knowledgebaseService->searchArticles($search, 50);
        } elseif ($categoryId > 0) {
            $articles = $this->knowledgebaseService->getArticlesByCategory($categoryId, 50);
        } else {
            $articles = $this->knowledgebaseService->getRecentArticles(50);
        }

        return JsonResponse::ok($response, ['articles' => array_map(
            static fn (array $article): array => self::mapArticle($article),
            $articles
        )]);
    }

    /**
     * GET /api/v1/knowledgebase/{id}
     */
    public function knowledgebaseArticle(Request $request, Response $response, array $args): Response
    {
        $article = $this->knowledgebaseService->getArticle((int)($args['id'] ?? 0));

        if ($article === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_NOT_FOUND,
                'Artigo não encontrado.',
                404
            );
        }

        return JsonResponse::ok($response, ['article' => self::mapArticle($article)]);
    }

    /**
     * GET /api/v1/announcements
     */
    public function announcements(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $id = (int)($params['id'] ?? 0);

        if ($id > 0) {
            $announcement = $this->announcementService->getAnnouncement($id);
            if ($announcement === null) {
                return JsonResponse::fail(
                    $response,
                    JsonResponse::CODE_NOT_FOUND,
                    'Anúncio não encontrado.',
                    404
                );
            }

            return JsonResponse::ok($response, ['announcement' => self::mapAnnouncement($announcement)]);
        }

        $limit = (int)($params['limit'] ?? 20);

        return JsonResponse::ok($response, [
            'announcements' => array_map(
                static fn (array $a): array => self::mapAnnouncement($a),
                $this->announcementService->getRecentAnnouncements($limit)
            ),
        ]);
    }

    /**
     * GET /api/v1/serverstatus
     */
    public function serverStatus(Request $request, Response $response): Response
    {
        return JsonResponse::ok($response, ['status' => $this->serverStatusService->getServerStatus()]);
    }

    /**
     * GET /api/v1/domains/pricing
     */
    public function domainPricing(Request $request, Response $response): Response
    {
        $tlds = $this->domainService->getPopularTLDs();

        $pricing = [];
        foreach ($tlds as $tld) {
            $pricing[] = [
                'tld' => $tld,
                'price' => $this->domainService->getTLDPrice($tld),
            ];
        }

        return JsonResponse::ok($response, ['pricing' => $pricing]);
    }

    /**
     * GET /api/v1/domains/check?domain=
     */
    public function domainCheck(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $domain = trim((string)($params['domain'] ?? ''));

        if ($domain === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique um domínio para verificar.',
                400
            );
        }

        $results = $this->domainService->checkAvailability($domain);

        return JsonResponse::ok($response, [
            'domain' => $domain,
            'results' => array_map(
                static fn (array $r): array => [
                    'domain' => $r['domain'] ?? '',
                    'available' => (bool)($r['available'] ?? false),
                    'tld' => $r['tld'] ?? '',
                ],
                $results
            ),
        ]);
    }

    // ─── Authenticated endpoints ─────────────────────────────────────────────

    /**
     * GET /api/v1/client/summary
     */
    public function clientSummary(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $client = $session['client'] ?? [];

        $summary = $this->billingService->getInvoiceSummary($clientId);
        $invoices = $this->billingService->getClientInvoices($clientId);
        $tickets = $this->ticketService->getClientTickets($clientId);
        $domains = $this->domainService->getClientDomains($clientId);

        return JsonResponse::ok($response, [
            'client' => [
                'id' => $clientId,
                'firstname' => $client['firstname'] ?? '',
                'lastname' => $client['lastname'] ?? '',
                'email' => $client['email'] ?? '',
            ],
            'billing' => [
                'creditBalance' => (float)($summary['credit_balance'] ?? 0),
                'unpaidTotal' => (float)($summary['unpaid_total'] ?? 0),
            ],
            'counts' => [
                'invoices' => count($invoices),
                'unpaidInvoices' => count(array_filter(
                    $invoices,
                    static fn (array $i): bool => ($i['status'] ?? '') === 'Unpaid'
                )),
                'tickets' => count($tickets),
                'openTickets' => count(array_filter(
                    $tickets,
                    static fn (array $t): bool => ($t['status'] ?? '') !== 'Closed'
                )),
                'domains' => count($domains),
            ],
        ]);
    }

    /**
     * GET /api/v1/client/invoices
     */
    public function clientInvoices(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $invoices = $this->billingService->getClientInvoices($clientId);

        return JsonResponse::ok($response, [
            'invoices' => array_map(
                static fn (array $i): array => self::mapInvoice($i),
                $invoices
            ),
        ]);
    }

    /**
     * GET /api/v1/client/invoices/{id}
     */
    public function clientInvoice(Request $request, Response $response, array $args): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $invoice = $this->billingService->getInvoiceWithItems((int)($args['id'] ?? 0));

        if ($invoice === null || (int)($invoice['userid'] ?? 0) !== $clientId) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_NOT_FOUND,
                'Fatura não encontrada.',
                404
            );
        }

        return JsonResponse::ok($response, [
            'invoice' => self::mapInvoice($invoice),
            'items' => array_map(
                static fn (array $item): array => [
                    'id' => (int)($item['id'] ?? 0),
                    'description' => $item['description'] ?? '',
                    'amount' => (float)($item['amount'] ?? 0),
                    'taxed' => (int)($item['taxed'] ?? 0),
                ],
                $invoice['items'] ?? []
            ),
        ]);
    }

    /**
     * GET /api/v1/client/domains
     */
    public function clientDomains(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $domains = $this->domainService->getClientDomains($clientId);

        return JsonResponse::ok($response, [
            'domains' => array_map(
                static fn (array $d): array => [
                    'id' => (int)($d['id'] ?? 0),
                    'domain' => $d['domain'] ?? '',
                    'status' => $d['status'] ?? '',
                    'expiryDate' => $d['expirydate'] ?? '',
                    'registrationDate' => $d['registrationdate'] ?? '',
                    'nextDueDate' => $d['nextduedate'] ?? '',
                    'period' => (int)($d['period'] ?? 0),
                ],
                $domains
            ),
        ]);
    }

    /**
     * GET /api/v1/client/tickets
     */
    public function clientTickets(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $tickets = $this->ticketService->getClientTickets($clientId);

        return JsonResponse::ok($response, [
            'tickets' => array_map(
                static fn (array $t): array => self::mapTicket($t),
                $tickets
            ),
        ]);
    }

    /**
     * GET /api/v1/client/tickets/{id}
     */
    public function clientTicket(Request $request, Response $response, array $args): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $ticket = $this->ticketService->getTicketWithReplies((int)($args['id'] ?? 0));

        if ($ticket === null || (int)($ticket['userid'] ?? 0) !== $clientId) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_NOT_FOUND,
                'Ticket não encontrado.',
                404
            );
        }

        return JsonResponse::ok($response, [
            'ticket' => self::mapTicket($ticket),
            'replies' => array_map(
                static fn (array $r): array => [
                    'id' => (int)($r['id'] ?? 0),
                    'name' => $r['name'] ?? '',
                    'message' => $r['message'] ?? '',
                    'date' => $r['date'] ?? '',
                    'adminReply' => (int)($r['adminreply'] ?? 0) === 1,
                ],
                $ticket['replies'] ?? []
            ),
        ]);
    }

    /**
     * GET /api/v1/client/orders
     */
    public function clientOrders(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $orders = $this->orderRepository->findByUserId($clientId);

        return JsonResponse::ok($response, [
            'orders' => array_map(
                static fn (array $order): array => [
                    'id' => (int)($order['id'] ?? 0),
                    'number' => (string)($order['id'] ?? 0),
                    'date' => $order['date'] ?? '',
                    'status' => $order['status'] ?? '',
                    'paymentMethod' => $order['paymentmethod'] ?? '',
                    'gateway' => $order['gateway'] ?? '',
                    'total' => (float)($order['total'] ?? 0),
                    'tax' => (float)($order['tax'] ?? 0),
                    'currency' => (int)($order['currency'] ?? 1),
                    'notes' => $order['notes'] ?? '',
                ],
                $orders
            ),
        ]);
    }

    /**
     * GET /api/v1/client/ssl
     *
     * The certificates belong to the domains the client owns.
     */
    public function clientSsl(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $domains = $this->domainService->getClientDomains($clientId);
        $autoRenew = $this->sslService->isAutoRenewalEnabled();

        $certificates = [];
        foreach ($domains as $domain) {
            $name = (string)($domain['domain'] ?? '');
            $certificate = $this->sslService->getCertificateInfo($name);

            $certificates[] = [
                'domain' => $name,
                'validFrom' => $certificate['valid_from'] ?? '',
                'validTo' => $certificate['valid_to'] ?? '',
                'daysRemaining' => (int)($certificate['days_remaining'] ?? 0),
                'issuer' => $certificate['issuer'] ?? '',
                'selfSigned' => (bool)($certificate['self_signed'] ?? false),
                'autoRenew' => $autoRenew,
            ];
        }

        return JsonResponse::ok($response, ['certificates' => $certificates]);
    }

    /**
     * GET /api/v1/client/affiliate
     */
    public function clientAffiliate(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $affiliate = $this->affiliateService->getAffiliateByUserId($clientId);

        if ($affiliate === null) {
            return JsonResponse::ok($response, [
                'affiliate' => null,
                'program' => [
                    'commissionRate' => 10.0,
                    'name' => 'Programa de Afiliados LINUXKAFÉ',
                ],
            ]);
        }

        $affiliateId = (int)$affiliate['id'];

        return JsonResponse::ok($response, [
            'affiliate' => [
                'id' => $affiliateId,
                'name' => $affiliate['name'] ?? '',
                'email' => $affiliate['email'] ?? '',
                'refCode' => $affiliate['refcode'] ?? '',
                'commissionRate' => (float)($affiliate['commission'] ?? 0),
                'balance' => (float)($affiliate['balance'] ?? 0),
                'unpaidBalance' => (float)($affiliate['unpaidbalance'] ?? 0),
                'referralCount' => $this->affiliateService->getReferralCount($affiliateId),
                'earnings' => $this->affiliateService->getEarnings($affiliateId),
                'link' => $this->affiliateService->getAffiliateLink($affiliateId),
            ],
            'referrals' => array_map(
                static fn (array $referral): array => [
                    'clientId' => (int)($referral['clientid'] ?? 0),
                    'date' => $referral['date'] ?? '',
                    'commission' => (float)($referral['commission'] ?? 0),
                ],
                $this->affiliateService->getReferrals($affiliateId)
            ),
        ]);
    }

    /**
     * GET /api/v1/home
     *
     * The landing screen: the summary plus the most recent news.
     */
    public function home(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $summary = $this->billingService->getInvoiceSummary($clientId);
        $announcements = $this->announcementService->getRecentAnnouncements(3);
        $articles = $this->knowledgebaseService->getRecentArticles(3);

        return JsonResponse::ok($response, [
            'client' => [
                'id' => $clientId,
                'firstname' => $session['client']['firstname'] ?? '',
                'lastname' => $session['client']['lastname'] ?? '',
                'email' => $session['client']['email'] ?? '',
            ],
            'billing' => [
                'creditBalance' => (float)($summary['credit_balance'] ?? 0),
                'unpaidTotal' => (float)($summary['unpaid_total'] ?? 0),
            ],
            'announcements' => array_map(
                static fn (array $a): array => [
                    'id' => (int)($a['id'] ?? 0),
                    'title' => $a['title'] ?? '',
                    'message' => $a['message'] ?? '',
                    'date' => $a['date'] ?? '',
                ],
                $announcements
            ),
            'articles' => array_map(
                static fn (array $article): array => [
                    'id' => (int)($article['id'] ?? 0),
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                ],
                $articles
            ),
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = trim((string)($data['email'] ?? ''));

        if ($email === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o email da sua conta.',
                400
            );
        }

        // The answer is the same whether or not the account exists: telling
        // the caller which emails are registered would leak the client list.
        $this->passwordResetService->requestReset($email);

        return JsonResponse::ok($response, [
            'message' => 'Se a conta existir, enviámos um e-mail com as instruções para redefinir a senha.',
        ]);
    }

    /**
     * POST /api/v1/client/tickets
     */
    public function createTicket(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $client = $session['client'] ?? [];
        $data = $request->getParsedBody();

        $subject = trim((string)($data['subject'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        if ($subject === '' || $message === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Assunto e mensagem são obrigatórios.',
                400
            );
        }

        $ticketId = $this->ticketService->createTicket([
            'userid' => $clientId,
            'name' => trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? '')),
            'email' => $client['email'] ?? '',
            'subject' => $subject,
            'message' => $message,
            'status' => 'Open',
            'priority' => TicketService::normalisePriority((string)($data['priority'] ?? 'Medium')),
            'deptid' => (int)($data['deptid'] ?? 1),
        ]);

        if ($ticketId === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_SERVER_ERROR,
                'Não foi possível criar o ticket.',
                500
            );
        }

        return JsonResponse::ok($response, ['id' => $ticketId], 201);
    }

    // ─── Mapping helpers ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private static function mapArticle(array $article): array
    {
        return [
            'id' => (int)($article['id'] ?? 0),
            'categoryId' => (int)($article['catid'] ?? 0),
            'categoryTitle' => $article['categorytitle'] ?? '',
            'title' => $article['title'] ?? '',
            'description' => $article['description'] ?? '',
            'content' => $article['content'] ?? '',
            'lastUpdated' => $article['lastupdated'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $announcement
     * @return array<string, mixed>
     */
    private static function mapAnnouncement(array $announcement): array
    {
        return [
            'id' => (int)($announcement['id'] ?? 0),
            'title' => $announcement['title'] ?? '',
            'message' => $announcement['message'] ?? '',
            'date' => $announcement['date'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    private static function mapInvoice(array $invoice): array
    {
        return [
            'id' => (int)($invoice['id'] ?? 0),
            'number' => (string)($invoice['id'] ?? 0),
            'status' => $invoice['status'] ?? '',
            'dateCreated' => $invoice['datecreated'] ?? '',
            'dueDate' => $invoice['duedate'] ?? '',
            'datePaid' => $invoice['datepaid'] ?? '',
            'total' => (float)($invoice['total'] ?? 0),
            'taxRate' => (float)($invoice['taxrate'] ?? 0),
            'currency' => (int)($invoice['currency'] ?? 1),
            'note' => $invoice['note'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $ticket
     * @return array<string, mixed>
     */
    private static function mapTicket(array $ticket): array
    {
        return [
            'id' => (int)($ticket['id'] ?? 0),
            'number' => (string)($ticket['id'] ?? 0),
            'subject' => $ticket['subject'] ?? '',
            'status' => $ticket['status'] ?? '',
            'priority' => $ticket['priority'] ?? '',
            'name' => $ticket['name'] ?? '',
            'email' => $ticket['email'] ?? '',
            'date' => $ticket['date'] ?? '',
            'lastReply' => $ticket['lastreply'] ?? '',
        ];
    }
}

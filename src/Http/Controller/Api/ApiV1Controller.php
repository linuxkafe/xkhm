<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Repository\ClientRepositoryInterface;
use Xkhm\Repository\OrderRepositoryInterface;
use Xkhm\Service\AdminPasswordResetService;
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
        private PasswordResetService $passwordResetService,
        private AdminPasswordResetService $adminPasswordResetService,
        private ClientRepositoryInterface $clientRepository,
        private \Xkhm\Service\MfaService $mfaService,
        private \Xkhm\Service\DeviceTrust $deviceTrust
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
        $deviceId = trim((string)($data['deviceId'] ?? ''));

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

        $clientId = (int)$client['id'];

        // A client with a second factor, logging in from a device the
        // server does not trust, gets a **challenge** — not a session.
        if ($this->mfaService->isEnrolled($clientId)) {
            $trusted = $this->deviceTrust->isTrusted(
                $this->trustedDeviceCookie($request),
                $deviceId
            );

            if (!$trusted) {
                return JsonResponse::ok($response, [
                    'mfaRequired' => true,
                    'challenge' => $this->mfaService->createChallenge($clientId),
                ]);
            }
        }

        $this->openSession($response, $client);

        return JsonResponse::ok($response, [
            'client' => [
                'id' => $clientId,
                'firstname' => $client['firstname'] ?? '',
                'lastname' => $client['lastname'] ?? '',
                'email' => $client['email'] ?? '',
                'status' => $client['status'] ?? '',
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/mfa
     *
     * The answer to a challenge: the code from the authenticator app.
     */
    public function mfaChallenge(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $challenge = trim((string)($data['challenge'] ?? ''));
        $code = trim((string)($data['code'] ?? ''));
        $deviceId = trim((string)($data['deviceId'] ?? ''));

        if ($challenge === '' || $code === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o código e o desafio.',
                400
            );
        }

        $clientId = $this->mfaService->resolveChallenge($challenge);

        if ($clientId === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_UNAUTHORIZED,
                'O pedido expirou. Inicie sessão de novo.',
                401
            );
        }

        if (!$this->mfaService->verifyCode($clientId, $code)) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_UNAUTHORIZED,
                'Código inválido.',
                401
            );
        }

        $client = $this->clientRepository->findById($clientId);

        if ($client === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_UNAUTHORIZED,
                'Utilizador ou senha inválidos.',
                401
            );
        }

        $this->openSession($response, $client);

        // The device is now trusted: the next login from it does not
        // ask for the code again.
        if ($deviceId !== '') {
            $this->issueTrust($response, $deviceId);
        }

        return JsonResponse::ok($response, [
            'client' => [
                'id' => $clientId,
                'firstname' => $client['firstname'] ?? '',
                'lastname' => $client['lastname'] ?? '',
                'email' => $client['email'] ?? '',
                'status' => $client['status'] ?? '',
            ],
        ]);
    }

    /**
     * GET /api/v1/client/security
     *
     * Whether the client has a second factor, and when it was set up.
     */
    public function clientSecurity(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        return JsonResponse::ok($response, [
            'enrolled' => $this->mfaService->isEnrolled($clientId),
        ]);
    }

    /**
     * POST /api/v1/client/security/enrol
     *
     * A new secret, and the QR the authenticator app reads. The secret
     * only becomes active once the client proves they can produce a
     * code with it.
     */
    public function clientSecurityEnrol(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $client = $this->clientRepository->findById($clientId);

        if ($client === null) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_NOT_FOUND,
                'Utilizador não encontrado.',
                404
            );
        }

        try {
            $enrolment = $this->mfaService->startEnrolment(
                $clientId,
                (string)($client['email'] ?? '')
            );
        } catch (\RuntimeException $e) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                $e->getMessage(),
                400
            );
        }

        return JsonResponse::ok($response, $enrolment);
    }

    /**
     * POST /api/v1/client/security/confirm
     *
     * The code from the authenticator app, proving the secret works.
     */
    public function clientSecurityConfirm(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);
        $data = $request->getParsedBody();
        $code = trim((string)($data['code'] ?? ''));

        if ($code === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o código.',
                400
            );
        }

        if (!$this->mfaService->confirmEnrolment($clientId, $code)) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Código inválido.',
                400
            );
        }

        return JsonResponse::ok($response, ['enrolled' => true]);
    }

    /**
     * POST /api/v1/client/security/disable
     */
    public function clientSecurityDisable(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        return JsonResponse::ok($response, [
            'enrolled' => !$this->mfaService->disable($clientId),
        ]);
    }

    /**
     * Open the session of a client.
     *
     * @param array<string, mixed> $client
     */
    private function openSession(Response $response, array $client): void
    {
        session_regenerate_id(true);
        $_SESSION['client_id'] = (int)$client['id'];
        $_SESSION['client'] = $client;
    }

    /**
     * The cookie of the trusted device, as the request carries it.
     */
    private function trustedDeviceCookie(Request $request): ?string
    {
        $cookies = $request->getCookieParams();

        $value = $cookies[\Xkhm\Service\DeviceTrust::COOKIE] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Tell the browser that this device is trusted.
     */
    private function issueTrust(Response $response, string $deviceId): void
    {
        $options = $this->deviceTrust->cookieOptions();

        setcookie(
            \Xkhm\Service\DeviceTrust::COOKIE,
            $this->deviceTrust->issue($deviceId),
            $options
        );
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
    /**
     * GET /api/v1/client/services
     *
     * The services the client has, with the **type** of each: the
     * product's group is the label the WHMCS shows ("Web Hosting",
     * "Backup"), and the raw type travels beside it.
     */
    public function clientServices(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        $services = $this->clientRepository->services($clientId);

        return JsonResponse::ok($response, [
            'services' => array_map(
                static function (array $service): array {
                    $group = trim((string)($service['groupname'] ?? ''));

                    if ($group === '') {
                        $group = (string)($service['producttype'] ?? '');
                    }

                    $name = trim((string)($service['productname'] ?? ''));

                    return [
                        'id' => (int)($service['id'] ?? 0),
                        'name' => $name !== '' ? $name : ('#' . (int)($service['id'] ?? 0)),
                        'type' => $group,
                        'rawType' => (string)($service['producttype'] ?? ''),
                        'domain' => (string)($service['domain'] ?? ''),
                        'username' => (string)($service['username'] ?? ''),
                        'server' => (string)($service['server'] ?? ''),
                        'status' => (string)($service['domainstatus'] ?? ''),
                        'amount' => (float)($service['amount'] ?? 0),
                        'billingCycle' => (string)($service['billingcycle'] ?? ''),
                        'nextDueDate' => (string)($service['nextduedate'] ?? ''),
                        'registrationDate' => (string)($service['regdate'] ?? ''),
                        'diskUsage' => (int)($service['diskusage'] ?? 0),
                        'diskLimit' => (int)($service['disklimit'] ?? 0),
                        'bandwidthUsage' => (int)($service['bwusage'] ?? 0),
                        'bandwidthLimit' => (int)($service['bwlimit'] ?? 0),
                    ];
                },
                $services
            ),
        ]);
    }

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
                // The contract is stable: the list is there, empty.
                'referrals' => [],
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
     *
     * The answer is **always** the same: revealing which addresses are
     * registered would turn the endpoint into an account oracle.
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

        $exists = $this->passwordResetService->requestReset($email);

        $payload = [
            'message' => 'Se a conta existir, enviámos as instruções para redefinir a senha.',
        ];

        // In development the token is shown, so that the flow can be
        // exercised without a mail server. In production the answer is
        // generic — the token was delivered by e-mail or left for the
        // administrator.
        if ($exists && $this->isDevelopment()) {
            $payload['developmentToken'] = $this->passwordResetService->lastToken();
            $payload['developmentDelivery'] = $this->passwordResetService->describeDelivery();
        }

        return JsonResponse::ok($response, $payload);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = trim((string)($data['token'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($token === '' || $password === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o código e a nova senha.',
                400
            );
        }

        if (strlen($password) < 8) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'A senha deve ter pelo menos 8 caracteres.',
                400
            );
        }

        if (!$this->passwordResetService->resetWithToken($token, $password)) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Código inválido ou expirado.',
                400
            );
        }

        return JsonResponse::ok($response, ['message' => 'Senha alterada com sucesso.']);
    }

    /**
     * POST /api/v1/admin/auth/forgot-password
     */
    public function adminForgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = trim((string)($data['username'] ?? ''));

        if ($username === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o nome de utilizador.',
                400
            );
        }

        $exists = $this->adminPasswordResetService->requestReset($username);

        $payload = [
            'message' => 'Se a conta existir, enviámos as instruções para redefinir a senha.',
        ];

        if ($exists && $this->isDevelopment()) {
            $payload['developmentToken'] = $this->adminPasswordResetService->lastToken();
            $payload['developmentDelivery'] = $this->adminPasswordResetService->describeDelivery();
        }

        return JsonResponse::ok($response, $payload);
    }

    /**
     * POST /api/v1/admin/auth/reset-password
     */
    public function adminResetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = trim((string)($data['token'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($token === '' || $password === '') {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Indique o código e a nova senha.',
                400
            );
        }

        if (strlen($password) < 8) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'A senha deve ter pelo menos 8 caracteres.',
                400
            );
        }

        if (!$this->adminPasswordResetService->resetWithToken($token, $password)) {
            return JsonResponse::fail(
                $response,
                JsonResponse::CODE_BAD_REQUEST,
                'Código inválido ou expirado.',
                400
            );
        }

        return JsonResponse::ok($response, ['message' => 'Senha alterada com sucesso.']);
    }

    /**
     * Is this a development deployment?
     */
    private function isDevelopment(): bool
    {
        $env = getenv('APP_ENV');

        return $env !== false && in_array(strtolower($env), ['dev', 'development', 'local'], true);
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

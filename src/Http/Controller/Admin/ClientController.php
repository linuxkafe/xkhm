<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Repository\CatalogueRepositoryPDO;
use Xkhm\Http\View\AdminLayout;

/**
 * The clients: the list and the detail.
 *
 * The detail is where the operator spends the day: the client's
 * invoices, orders, domains, tickets, transactions and hosting, all in
 * one place, as in the WHMCS.
 */
class ClientController extends AdminController
{
    /**
     * The states a client can be in.
     */
    private const STATUSES = ['Active', 'Pending', 'Inactive', 'Suspended'];

    /**
     * GET /admin/clients.php
     */
    public function index(Request $request, Response $response): Response
    {
        $action = (string)($request->getQueryParams()['action'] ?? '');

        // Com `action=addservice` e' o formulario de associar um servico
        // ao cliente, e nao o detalhe.
        if ($action === 'addservice' && $this->id($request) !== null) {
            if (strtoupper((string)$request->getMethod()) === 'POST') {
                return $this->addService($request, $response);
            }

            return $this->addServiceForm($request, $response);
        }

        // With an `id` in the URL it is the detail, not the list. The
        // router cannot tell them apart: the path is the same.
        if ($this->id($request) !== null) {
            return $this->detail($request, $response);
        }

        // An `id` that is present and malformed is a mistake in the
        // request, not a request for the list.
        if ($this->hasInvalidId($request)) {
            return $this->badRequest($response, 'O cliente', $this->adminName());
        }

        $search = $this->searchTerm($request);
        $page = $this->page($request);

        $total = $this->repository->countClients($search);
        $clients = $this->repository->listClients(
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'id' => '<a href="/admin/clients.php?id=' . (int)$client['id'] . '">#'
                    . (int)$client['id'] . '</a>',
                'name' => sprintf(
                    '<a href="/admin/clients.php?id=%d">%s %s</a>',
                    (int)$client['id'],
                    $this->e($client['firstname']),
                    $this->e($client['lastname'])
                ),
                'email' => $this->e($client['email']),
                'company' => $this->e($client['companyname'] ?? ''),
                'credit' => $this->money($client['credit'] ?? 0),
                'status' => $this->badge((string)$client['status']),
                'created' => $this->date($client['datecreated'] ?? null),
            ];
        }

        // No "new client" button: there is no creation form yet, and a
        // button that goes nowhere is worse than no button at all.
        $body = AdminLayout::header('Clientes')
            . $this->filters('/admin/clients.php', $search, null, self::STATUSES, 'Nome ou email')
            . $this->table(
                [
                    ['label' => 'Id', 'value' => 'id'],
                    ['label' => 'Nome', 'value' => 'name'],
                    ['label' => 'Email', 'value' => 'email'],
                    ['label' => 'Empresa', 'value' => 'company'],
                    ['label' => 'Crédito', 'value' => 'credit', 'numeric' => true],
                    ['label' => 'Estado', 'value' => 'status'],
                    ['label' => 'Criado', 'value' => 'created'],
                ],
                $rows,
                'Nenhum cliente encontrado.'
            )
            . $this->pager($total, $page, array_filter([
                'q' => $search,
            ]));

        return $this->render($response, 'Clientes', 'clients', $body, $this->adminName());
    }

    /**
     * The services of a client, **grouped by type**.
     *
     * `tblhosting` holds every service — the web hosting, the backups,
     * the servers. What tells them apart is the product they belong to,
     * and the label the WHMCS shows the client is the product's
     * **group**.
     */
    private function servicesSection(array $services): string
    {
        if ($services === []) {
            return '<section class="panel"><div class="panel-heading">Serviços</div>'
                . '<div class="panel-body">'
                . '<p class="text-muted">Este cliente não tem serviços.</p>'
                . '</div></section>';
        }

        // Agrupados pela familia do produto.
        $groups = [];
        foreach ($services as $service) {
            $group = trim((string)($service['groupname'] ?? ''));

            if ($group === '') {
                $group = (string)($service['producttype'] ?? '');
            }

            $groups[$group][] = $service;
        }

        ksort($groups);

        $html = '<h2 class="admin-subtitle">Serviços</h2>';

        foreach ($groups as $group => $groupServices) {
            $rows = [];

            foreach ($groupServices as $service) {
                $rows[] = [
                    'service' => sprintf(
                        '<a href="/admin/services.php?id=%d">%s</a>',
                        (int)$service['id'],
                        $this->e($this->serviceName($service))
                    ),
                    'type' => $this->e($group),
                    'domain' => $this->e($service['domain'] ?? ''),
                    'amount' => $this->money($service['amount'] ?? 0),
                    'next' => $this->date($service['nextduedate'] ?? null),
                    'status' => $this->badge((string)($service['domainstatus'] ?? '')),
                    'actions' => sprintf(
                        '<a class="btn btn-sm" href="/admin/services.php?action=edit&id=%d">Editar</a>',
                        (int)$service['id']
                    ),
                ];
            }

            $html .= '<section class="panel"><div class="panel-heading">'
                . $this->e($group) . '</div><div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Serviço', 'value' => 'service'],
                        ['label' => 'Tipo', 'value' => 'type'],
                        ['label' => 'Domínio', 'value' => 'domain'],
                        ['label' => 'Valor', 'value' => 'amount', 'numeric' => true],
                        ['label' => 'Próxima renovação', 'value' => 'next'],
                        ['label' => 'Estado', 'value' => 'status'],
                        ['label' => 'Ações', 'value' => 'actions'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        return $html;
    }

    /**
     * The form to associate a new service to a client.
     */
    public function addServiceForm(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        $client = $this->repository->findClient((int)$id);

        if ($client === null) {
            return $this->notFound($response, 'O cliente', $admin);
        }

        $catalogue = new \Xkhm\Repository\CatalogueRepositoryPDO();
        $products = $catalogue->products();

        $options = '';

        foreach ($products as $product) {
            if ((string)($product['hidden'] ?? '') === 'on') {
                continue;
            }

            $prices = $product['prices'] ?? [];
            $firstCycle = '';

            foreach (CatalogueRepositoryPDO::CYCLES as $column => $label) {
                if ((float)($prices[$column] ?? -1) >= 0) {
                    $firstCycle = $label;
                    break;
                }
            }

            $price = (float)($prices[$firstCycle !== '' ? strtolower(str_replace(' ', '', $firstCycle)) : 'annually'] ?? -1);

            if ($price < 0) {
                continue;
            }

            $options .= sprintf(
                '<option value="%d" data-cycle="%s" data-price="%s">%s — %s</option>',
                (int)$product['id'],
                $this->e($firstCycle),
                $this->e(number_format($price, 2, '.', '')),
                $this->e((string)($product['name'] ?? '')),
                $this->e((string)($product['groupname'] ?? ''))
            );
        }

        $today = date('Y-m-d');

        $body = AdminLayout::header(
            sprintf('Novo serviço — %s %s', $client['firstname'], $client['lastname']),
            ['Cliente' => '/admin/clients.php?id=' . $id]
        );

        $body .= '<section class="panel"><div class="panel-heading">Novo serviço</div><div class="panel-body">'
            . '<form method="post" action="/admin/clients.php?id=' . $id . '&action=addservice" class="admin-form">'
            . '<div class="form-group"><label for="service-product">Produto</label>'
            . '<select id="service-product" name="productid" class="form-control" required>'
            . $options . '</select></div>'
            . '<div class="form-group"><label for="service-cycle">Ciclo</label>'
            . '<select id="service-cycle" name="billingcycle" class="form-control">'
            . '<option value="Monthly">Mensal</option>'
            . '<option value="Quarterly">Trimestral</option>'
            . '<option value="Semi Annually">Semestral</option>'
            . '<option value="Annually" selected>Anual</option>'
            . '<option value="Biennially">Bienal</option>'
            . '<option value="Triennially">Trienal</option>'
            . '</select></div>'
            . '<div class="form-group"><label for="service-price">Valor (€)</label>'
            . '<input type="text" id="service-price" name="amount" class="form-control" value="0.00" inputmode="decimal" required></div>'
            . '<div class="form-group"><label for="service-renewal">Próxima renovação</label>'
            . '<input type="date" id="service-renewal" name="nextduedate" class="form-control" value="' . $this->e(self::renewalDefault('Annually')) . '" required></div>'
            . '<div class="form-group"><label for="service-domain">Domínio</label>'
            . '<input type="text" id="service-domain" name="domain" class="form-control"></div>'
            . '<button type="submit" class="btn btn-primary">Associar o serviço</button>'
            . '</form></div></section>';

        return $this->render($response, 'Novo serviço', 'clients', $body, $admin);
    }

    /**
     * POST /admin/clients.php?id=N&action=addservice
     */
    public function addService(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O cliente', $admin);
        }

        $productId = (int)($data['productid'] ?? 0);
        $cycle = (string)($data['billingcycle'] ?? 'Annually');
        $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? '0'));
        $domain = trim((string)($data['domain'] ?? ''));
        $renewal = (string)($data['nextduedate'] ?? '');

        if ($productId <= 0) {
            return $this->badRequest($response, 'O serviço', $admin);
        }

        $catalogue = new \Xkhm\Repository\CatalogueRepositoryPDO();
        $product = $catalogue->product($productId);

        if ($product === null) {
            return $this->notFound($response, 'O produto', $admin);
        }

        // O valor por omissão é **hoje + o ciclo** — assim o serviço
        // recém-associado não é apanhado pelo ciclo de faturação (que
        // gera a fatura de renovação) no mesmo dia.
        if ($renewal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal)) {
            $renewal = self::renewalDefault($cycle);
        }

        $services = new \Xkhm\Repository\ServiceRepositoryPDO();

        $services->create([
            'userid' => $id,
            'orderid' => 0,
            'packageid' => $productId,
            'server' => (int)($product['servergroup'] ?? 0),
            'regdate' => date('Y-m-d'),
            'domain' => $domain,
            'paymentmethod' => '',
            'firstpaymentamount' => $amount,
            'amount' => $amount,
            'billingcycle' => $cycle,
            'nextduedate' => $renewal,
            'nextinvoicedate' => $renewal,
            'domainstatus' => 'Active',
            'username' => '',
            'password' => '',
            'notes' => 'Associado pelo operador',
            'subscriptionid' => '',
            'promoid' => 0,
            'suspendreason' => '',
            'overideautosuspend' => '',
            'overidesuspenduntil' => '0000-00-00',
            'dedicatedip' => '',
            'assignedips' => '',
            'ns1' => '',
            'ns2' => '',
            'diskusage' => 0,
            'disklimit' => 0,
            'bwusage' => 0,
            'bwlimit' => 0,
        ]);

        return $response
            ->withHeader('Location', '/admin/clients.php?id=' . $id . '&added=1')
            ->withStatus(302);
    }

    /**
     * The default renewal date for a cycle.
     *
     * The renewal of a newly associated service is, by default, one
     * cycle away — so that the billing cycle (which raises the renewal
     * invoice) does not pick it up on the same day.
     */
    private static function renewalDefault(string $cycle): string
    {
        $steps = [
            'Monthly' => '+1 month',
            'Quarterly' => '+3 months',
            'Semi Annually' => '+6 months',
            'Annually' => '+1 year',
            'Biennially' => '+2 years',
            'Triennially' => '+3 years',
        ];

        $step = $steps[$cycle] ?? '+1 year';

        return date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $step));
    }

    /**
     * The name of a service.
     *
     * The product's name is the real one («LINUXKONECT XK10»). A
     * service without a product falls back to its identifier.
     */
    private function serviceName(array $service): string
    {
        $name = trim((string)($service['productname'] ?? ''));

        return $name !== '' ? $name : '#' . (int)($service['id'] ?? 0);
    }

    /**
     * GET /admin/clients.php?id=N
     */
    public function detail(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O cliente', $admin);
        }

        $client = $this->repository->findClient($id);

        if ($client === null) {
            return $this->notFound($response, 'O cliente', $admin);
        }

        // Billing totals
        $billing = $this->repository->clientBillingTotals($id);

        // Gather data for each tab
        $tabs = [];

        // Services (always first)
        $services = $this->repository->clientServices($id);
        if ($services !== []) {
            $tabs['services'] = [
                'label' => 'Serviços',
                'content' => $this->servicesSection($services),
            ];
        }

        // Domains
        $domainRows = [];
        foreach ($this->repository->clientDomains($id) as $domain) {
            $domainRows[] = [
                'domain' => $this->e($domain['domain']),
                'registrar' => $this->e($domain['registrar'] ?? ''),
                'expiry' => $this->date($domain['expirydate'] ?? null),
                'status' => $this->badge((string)$domain['status']),
                'actions' => sprintf(
                    '<a class="btn btn-sm" href="/admin/domains.php?id=%d">Editar</a>',
                    (int)$domain['id']
                ),
            ];
        }
        if ($domainRows !== []) {
            $tabs['domains'] = [
                'label' => 'Domínios',
                'content' => $this->table(
                    [
                        ['label' => 'Domínio', 'value' => 'domain'],
                        ['label' => 'Registar', 'value' => 'registrar'],
                        ['label' => 'Expira', 'value' => 'expiry'],
                        ['label' => 'Estado', 'value' => 'status'],
                        ['label' => 'Ações', 'value' => 'actions'],
                    ],
                    $domainRows,
                    'Este cliente não tem domínios.'
                ),
            ];
        }

        // Invoices
        $invoiceRows = [];
        foreach ($this->repository->clientInvoices($id) as $invoice) {
            $invoiceRows[] = [
                'number' => sprintf(
                    '<a href="/admin/invoices.php?id=%d">%s</a>',
                    (int)$invoice['id'],
                    $this->e($invoice['invoicenum'])
                ),
                'date' => $this->date($invoice['datecreated'] ?? null),
                'total' => $this->money($invoice['total']),
                'status' => $this->badge((string)$invoice['status']),
            ];
        }
        if ($invoiceRows !== []) {
            $tabs['invoices'] = [
                'label' => 'Faturas',
                'content' => $this->table(
                    [
                        ['label' => 'Fatura', 'value' => 'number'],
                        ['label' => 'Data', 'value' => 'date'],
                        ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $invoiceRows,
                    'Este cliente não tem faturas.'
                ),
            ];
        }

        // Orders
        $orderRows = [];
        foreach ($this->repository->clientOrders($id) as $order) {
            $orderRows[] = [
                'number' => sprintf(
                    '<a href="/admin/orders.php?id=%d">%s</a>',
                    (int)$order['id'],
                    $this->e($order['ordernum'])
                ),
                'date' => $this->datetime($order['date'] ?? null),
                'total' => $this->money($order['total']),
                'status' => $this->badge((string)$order['status']),
            ];
        }
        if ($orderRows !== []) {
            $tabs['orders'] = [
                'label' => 'Pedidos',
                'content' => $this->table(
                    [
                        ['label' => 'Pedido', 'value' => 'number'],
                        ['label' => 'Data', 'value' => 'date'],
                        ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $orderRows,
                    'Este cliente não tem pedidos.'
                ),
            ];
        }

        // Tickets
        $ticketRows = [];
        foreach ($this->repository->clientTickets($id) as $ticket) {
            $ticketRows[] = [
                'subject' => sprintf(
                    '<a href="/admin/tickets.php?id=%d">%s</a>',
                    (int)$ticket['id'],
                    $this->e($ticket['subject'])
                ),
                'date' => $this->datetime($ticket['date'] ?? null),
                'status' => $this->badge((string)$ticket['status']),
            ];
        }
        if ($ticketRows !== []) {
            $tabs['tickets'] = [
                'label' => 'Tickets',
                'content' => $this->table(
                    [
                        ['label' => 'Assunto', 'value' => 'subject'],
                        ['label' => 'Aberto', 'value' => 'date'],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $ticketRows,
                    'Este cliente não tem tickets.'
                ),
            ];
        }

        // Transactions
        $txRows = [];
        foreach ($this->repository->clientTransactions($id) as $transaction) {
            $txRows[] = [
                'date' => $this->date($transaction['date'] ?? null),
                'description' => $this->e($transaction['description'] ?? ''),
                'gateway' => $this->e($transaction['gateway'] ?? ''),
                'in' => $this->money($transaction['amountin'] ?? 0),
                'out' => $this->money($transaction['amountout'] ?? 0),
            ];
        }
        if ($txRows !== []) {
            $tabs['transactions'] = [
                'label' => 'Transações',
                'content' => $this->table(
                    [
                        ['label' => 'Data', 'value' => 'date'],
                        ['label' => 'Descrição', 'value' => 'description'],
                        ['label' => 'Via', 'value' => 'gateway'],
                        ['label' => 'Entrada', 'value' => 'in', 'numeric' => true],
                        ['label' => 'Saída', 'value' => 'out', 'numeric' => true],
                    ],
                    $txRows,
                    'Este cliente não tem transações.'
                ),
            ];
        }

        $body = AdminLayout::header(
            sprintf('%s %s', $client['firstname'], $client['lastname']),
            ['Clientes' => '/admin/clients.php']
        );

        // Client header details + billing summary
        $fields = [
            'Email' => $this->e($client['email']),
            'Empresa' => $this->e($client['companyname'] ?? ''),
            'Telefone' => $this->e($client['phone'] ?? ''),
            'Morada' => trim(sprintf(
                '%s %s, %s %s, %s',
                $this->e($client['address1'] ?? ''),
                $this->e($client['address2'] ?? ''),
                $this->e($client['postcode'] ?? ''),
                $this->e($client['city'] ?? ''),
                $this->e($client['country'] ?? '')
            ), ' ,'),
            'Crédito' => $this->money($client['credit'] ?? 0),
            'Estado' => $this->badge((string)$client['status']),
            'Criado' => $this->date($client['datecreated'] ?? null),
            'Último acesso' => $this->datetime($client['lastlogin'] ?? null),
        ];

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        foreach ($fields as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl></div></section>';

        // Billing summary panel (right side style via CSS grid)
        $body .= '<section class="panel"><div class="panel-heading">Faturação</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        $body .= '<div><dt>Total histórico</dt><dd>' . $this->money($billing['total']) . '</dd></div>';
        $body .= '<div><dt>Total ' . date('Y') . '</dt><dd>' . $this->money($billing['year']) . '</dd></div>';
        $body .= '</dl></div></section>';

        // Tabs markup
        if ($tabs !== []) {
            $body .= '<div class="admin-tabs">';
            foreach ($tabs as $key => $tab) {
                $active = $key === array_key_first($tabs) ? ' active' : '';
                $body .= sprintf(
                    '<button class="admin-tab-btn%s" data-tab="%s">%s</button>',
                    $active,
                    $key,
                    $this->e($tab['label'])
                );
            }
            $body .= '</div>';

            foreach ($tabs as $key => $tab) {
                $active = $key === array_key_first($tabs) ? ' active' : '';
                $body .= sprintf(
                    '<div class="admin-tab-panel%s" data-tab="%s">%s</div>',
                    $active,
                    $key,
                    $tab['content']
                );
            }
        }

        // Success message after adding service
        if (isset($request->getQueryParams()['added'])) {
            $body .= '<div class="alert alert-success">O serviço foi associado ao cliente.</div>';
        }

        // Associate service action
        $body .= '<div class="admin-actions">'
            . '<a class="btn btn-primary" href="/admin/clients.php?id=' . $id . '&action=addservice">Associar um serviço</a>'
            . '</div>';

        return $this->render(
            $response,
            sprintf('%s %s', $client['firstname'], $client['lastname']),
            'clients',
            $body,
            $admin
        );
    }
}

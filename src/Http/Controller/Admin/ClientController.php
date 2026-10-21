<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
                    ],
                    $rows
                )
                . '</div></section>';
        }

        return $html;
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

        $body = AdminLayout::header(
            sprintf('%s %s', $client['firstname'], $client['lastname']),
            ['Clientes' => '/admin/clients.php']
        );

        // ── Os dados ──
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

        // ── As faturas ──
        $rows = [];
        foreach ($this->repository->clientInvoices($id) as $invoice) {
            $rows[] = [
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

        $body .= '<section class="panel"><div class="panel-heading">Faturas</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Fatura', 'value' => 'number'],
                    ['label' => 'Data', 'value' => 'date'],
                    ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Este cliente não tem faturas.'
            )
            . '</div></section>';

        // ── Os pedidos ──
        $rows = [];
        foreach ($this->repository->clientOrders($id) as $order) {
            $rows[] = [
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

        $body .= '<section class="panel"><div class="panel-heading">Pedidos</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Pedido', 'value' => 'number'],
                    ['label' => 'Data', 'value' => 'date'],
                    ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Este cliente não tem pedidos.'
            )
            . '</div></section>';

        // ── Os dominios ──
        $rows = [];
        foreach ($this->repository->clientDomains($id) as $domain) {
            $rows[] = [
                'domain' => $this->e($domain['domain']),
                'registrar' => $this->e($domain['registrar'] ?? ''),
                'expiry' => $this->date($domain['expirydate'] ?? null),
                'status' => $this->badge((string)$domain['status']),
            ];
        }

        $body .= '<section class="panel"><div class="panel-heading">Domínios</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Domínio', 'value' => 'domain'],
                    ['label' => 'Registar', 'value' => 'registrar'],
                    ['label' => 'Expira', 'value' => 'expiry'],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Este cliente não tem domínios.'
            )
            . '</div></section>';

        // ── Os tickets ──
        $rows = [];
        foreach ($this->repository->clientTickets($id) as $ticket) {
            $rows[] = [
                'subject' => sprintf(
                    '<a href="/admin/tickets.php?id=%d">%s</a>',
                    (int)$ticket['id'],
                    $this->e($ticket['subject'])
                ),
                'date' => $this->datetime($ticket['date'] ?? null),
                'status' => $this->badge((string)$ticket['status']),
            ];
        }

        $body .= '<section class="panel"><div class="panel-heading">Tickets</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Assunto', 'value' => 'subject'],
                    ['label' => 'Aberto', 'value' => 'date'],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Este cliente não tem tickets.'
            )
            . '</div></section>';

        // ── As transacoes ──
        $rows = [];
        foreach ($this->repository->clientTransactions($id) as $transaction) {
            $rows[] = [
                'date' => $this->date($transaction['date'] ?? null),
                'description' => $this->e($transaction['description'] ?? ''),
                'gateway' => $this->e($transaction['gateway'] ?? ''),
                'in' => $this->money($transaction['amountin'] ?? 0),
                'out' => $this->money($transaction['amountout'] ?? 0),
            ];
        }

        $body .= '<section class="panel"><div class="panel-heading">Transações</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Data', 'value' => 'date'],
                    ['label' => 'Descrição', 'value' => 'description'],
                    ['label' => 'Via', 'value' => 'gateway'],
                    ['label' => 'Entrada', 'value' => 'in', 'numeric' => true],
                    ['label' => 'Saída', 'value' => 'out', 'numeric' => true],
                ],
                $rows,
                'Este cliente não tem transações.'
            )
            . '</div></section>';

        // ── Os servicos ──
        $body .= $this->servicesSection($this->repository->clientServices($id));

        return $this->render(
            $response,
            sprintf('%s %s', $client['firstname'], $client['lastname']),
            'clients',
            $body,
            $admin
        );
    }
}

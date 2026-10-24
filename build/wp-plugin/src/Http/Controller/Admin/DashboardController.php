<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;

/**
 * The dashboard, the global search and the configuration.
 *
 * These three are the "read the state" pages: they have nothing in
 * common but the fact that they do not change anything.
 */
class DashboardController extends AdminController
{
    /**
     * GET /admin
     *
     * The numbers the operator wants at nine in the morning: who owes
     * what, what is open, who is asking for help.
     */
    public function index(Request $request, Response $response): Response
    {
        $data = $this->repository->dashboard();

        $body = AdminLayout::header('Painel');

        $body .= '<div class="admin-stats">';

        $body .= $this->stat('Clientes', (string)$data['clients']['total'], [
            'Activos: ' . $data['clients']['active'],
        ]);

        $body .= $this->stat(
            'Faturas pagas',
            (string)$data['invoices']['paid'],
            [
                'Recebido: ' . $this->money($data['invoices']['revenue']),
                'Por receber: ' . $this->money($data['invoices']['outstanding']),
                'Em atraso: ' . $data['invoices']['overdue'],
            ],
            '/admin/invoices.php?status=Unpaid'
        );

        $body .= $this->stat(
            'Tickets abertos',
            (string)$data['tickets']['open'],
            ['Total: ' . $data['tickets']['total']],
            '/admin/tickets.php?status=Open'
        );

        $body .= $this->stat(
            'Pedidos ativos',
            (string)$data['orders']['active'],
            ['Total: ' . $data['orders']['total']],
            '/admin/orders.php?status=Active'
        );

        $body .= $this->stat(
            'Domínios',
            (string)$data['domains']['total'],
            [],
            '/admin/domains.php'
        );

        $body .= '</div>';

        // ── Os últimos clientes ──
        $body .= '<div class="admin-columns">';

        $body .= '<section class="panel"><div class="panel-heading">Últimos clientes</div>'
            . '<div class="panel-body">';

        $rows = [];
        foreach ($data['recent_clients'] as $client) {
            $rows[] = [
                'name' => sprintf(
                    '<a href="/admin/clients.php?id=%d">%s %s</a>',
                    (int)$client['id'],
                    $this->e($client['firstname']),
                    $this->e($client['lastname'])
                ),
                'email' => $this->e($client['email']),
                'date' => $this->date($client['datecreated'] ?? null),
            ];
        }

        $body .= $this->table(
            [
                ['label' => 'Nome', 'value' => 'name'],
                ['label' => 'Email', 'value' => 'email'],
                ['label' => 'Criado', 'value' => 'date'],
            ],
            $rows,
            'Ainda não há clientes.'
        );

        $body .= '</div></section>';

        // ── As últimas faturas ──
        $body .= '<section class="panel"><div class="panel-heading">Últimas faturas</div>'
            . '<div class="panel-body">';

        $rows = [];
        foreach ($data['recent_invoices'] as $invoice) {
            $rows[] = [
                'number' => sprintf(
                    '<a href="/admin/invoices.php?id=%d">%s</a>',
                    (int)$invoice['id'],
                    $this->e($this->invoiceNumber($invoice))
                ),
                'total' => $this->money($invoice['total']),
                'status' => $this->badge((string)$invoice['status']),
            ];
        }

        $body .= $this->table(
            [
                ['label' => 'Fatura', 'value' => 'number'],
                ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                ['label' => 'Estado', 'value' => 'status'],
            ],
            $rows,
            'Ainda não há faturas.'
        );

        $body .= '</div></section>';

        // ── Os tickets recentes ──
        $body .= '<section class="panel"><div class="panel-heading">Tickets recentes</div>'
            . '<div class="panel-body">';

        $rows = [];
        foreach ($data['recent_tickets'] as $ticket) {
            $rows[] = [
                'subject' => sprintf(
                    '<a href="/admin/tickets.php?id=%d">%s</a>',
                    (int)$ticket['id'],
                    $this->e($ticket['subject'])
                ),
                'status' => $this->badge((string)$ticket['status']),
                'date' => $this->datetime($ticket['date'] ?? null),
            ];
        }

        $body .= $this->table(
            [
                ['label' => 'Assunto', 'value' => 'subject'],
                ['label' => 'Estado', 'value' => 'status'],
                ['label' => 'Aberto', 'value' => 'date'],
            ],
            $rows,
            'Ainda não há tickets.'
        );

        $body .= '</div></section>';

        $body .= '</div>';

        return $this->render($response, 'Painel', 'dashboard', $body, $this->adminName());
    }

    /**
     * GET /admin/search.php
     *
     * The global search: clients, invoices, tickets, domains and
     * orders, all at once.
     */
    public function search(Request $request, Response $response): Response
    {
        $term = $this->searchTerm($request);
        $admin = $this->adminName();

        if ($term === null) {
            $body = AdminLayout::header('Pesquisar')
                . '<form class="admin-filters" method="get" action="/admin/search.php">'
                . '<input type="search" name="q" placeholder="Cliente, fatura, ticket ou domínio"'
                . ' aria-label="Termo de pesquisa" autofocus>'
                . '<button type="submit" class="btn btn-primary">Pesquisar</button>'
                . '</form>';

            return $this->render($response, 'Pesquisar', 'search', $body, $admin);
        }

        $results = $this->repository->globalSearch($term);

        $body = AdminLayout::header('Pesquisar: ' . $term)
            . '<form class="admin-filters" method="get" action="/admin/search.php">'
            . '<input type="search" name="q" value="' . $this->e($term) . '"'
            . ' placeholder="Cliente, fatura, ticket ou domínio" aria-label="Termo de pesquisa">'
            . '<button type="submit" class="btn btn-primary">Pesquisar</button>'
            . '</form>';

        $total = 0;
        foreach ($results as $rows) {
            $total += count($rows);
        }

        if ($total === 0) {
            $body .= '<p class="text-muted">Nada encontrado para "' . $this->e($term) . '".</p>';

            return $this->render($response, 'Pesquisar', 'search', $body, $admin);
        }

        // ── Clientes ──
        if ($results['clients'] !== []) {
            $rows = [];
            foreach ($results['clients'] as $client) {
                $rows[] = [
                    'name' => sprintf(
                        '<a href="/admin/clients.php?id=%d">%s %s</a>',
                        (int)$client['id'],
                        $this->e($client['firstname']),
                        $this->e($client['lastname'])
                    ),
                    'email' => $this->e($client['email']),
                    'status' => $this->badge((string)$client['status']),
                ];
            }

            $body .= '<section class="panel"><div class="panel-heading">Clientes</div>'
                . '<div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Nome', 'value' => 'name'],
                        ['label' => 'Email', 'value' => 'email'],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        // ── Faturas ──
        if ($results['invoices'] !== []) {
            $rows = [];
            foreach ($results['invoices'] as $invoice) {
                $rows[] = [
                    'number' => sprintf(
                        '<a href="/admin/invoices.php?id=%d">%s</a>',
                        (int)$invoice['id'],
                        $this->e($this->invoiceNumber($invoice))
                    ),
                    'total' => $this->money($invoice['total']),
                    'status' => $this->badge((string)$invoice['status']),
                ];
            }

            $body .= '<section class="panel"><div class="panel-heading">Faturas</div>'
                . '<div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Fatura', 'value' => 'number'],
                        ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        // ── Tickets ──
        if ($results['tickets'] !== []) {
            $rows = [];
            foreach ($results['tickets'] as $ticket) {
                $rows[] = [
                    'subject' => sprintf(
                        '<a href="/admin/tickets.php?id=%d">%s</a>',
                        (int)$ticket['id'],
                        $this->e($ticket['subject'])
                    ),
                    'client' => $this->e($ticket['name']),
                    'status' => $this->badge((string)$ticket['status']),
                ];
            }

            $body .= '<section class="panel"><div class="panel-heading">Tickets</div>'
                . '<div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Assunto', 'value' => 'subject'],
                        ['label' => 'Cliente', 'value' => 'client'],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        // ── Dominios ──
        if ($results['domains'] !== []) {
            $rows = [];
            foreach ($results['domains'] as $domain) {
                $rows[] = [
                    'domain' => $this->e($domain['domain']),
                    'registrar' => $this->e($domain['registrar']),
                    'expiry' => $this->date($domain['expirydate'] ?? null),
                ];
            }

            $body .= '<section class="panel"><div class="panel-heading">Domínios</div>'
                . '<div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Domínio', 'value' => 'domain'],
                        ['label' => 'Registar', 'value' => 'registrar'],
                        ['label' => 'Expira', 'value' => 'expiry'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        // ── Pedidos ──
        if ($results['orders'] !== []) {
            $rows = [];
            foreach ($results['orders'] as $order) {
                $rows[] = [
                    'number' => sprintf(
                        '<a href="/admin/orders.php?id=%d">%s</a>',
                        (int)$order['id'],
                        $this->e($order['ordernum'])
                    ),
                    'total' => $this->money($order['total']),
                    'status' => $this->badge((string)$order['status']),
                ];
            }

            $body .= '<section class="panel"><div class="panel-heading">Pedidos</div>'
                . '<div class="panel-body">'
                . $this->table(
                    [
                        ['label' => 'Pedido', 'value' => 'number'],
                        ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                        ['label' => 'Estado', 'value' => 'status'],
                    ],
                    $rows
                )
                . '</div></section>';
        }

        return $this->render($response, 'Pesquisar', 'search', $body, $admin);
    }

    /**
     * GET /admin/config.php
     *
     * The configuration, read-only. The WHMCS keeps it in
     * `tblconfiguration`, as `setting`/`value` pairs.
     */
    public function config(Request $request, Response $response): Response
    {
        $settings = $this->repository->configuration();

        $body = AdminLayout::header('Configuração')
            . '<p class="text-muted">'
            . count($settings) . ' definições, tal como estão na base de dados. '
            . 'Esta página é de leitura: alterar a configuração é trabalho à parte.'
            . '</p>';

        $rows = [];
        foreach ($settings as $setting) {
            $rows[] = [
                'setting' => '<code>' . $this->e($setting['setting']) . '</code>',
                'value' => $this->e($setting['value']),
            ];
        }

        $body .= $this->table(
            [
                ['label' => 'Definição', 'value' => 'setting'],
                ['label' => 'Valor', 'value' => 'value'],
            ],
            $rows
        );

        return $this->render($response, 'Configuração', 'config', $body, $this->adminName());
    }

    /**
     * A number, with its context.
     *
     * @param list<string> $lines
     */
    private function stat(string $label, string $value, array $lines = [], string $href = ''): string
    {
        $valueHtml = '<strong class="admin-stat-value">' . $this->e($value) . '</strong>';
        $inner = $href !== ''
            ? sprintf('<a href="%s">%s</a>', $this->e($href), $valueHtml)
            : $valueHtml;

        $context = '';
        foreach ($lines as $line) {
            $context .= '<span class="admin-stat-line">' . $line . '</span>';
        }

        return '<div class="admin-stat"><span class="admin-stat-label">'
            . $this->e($label) . '</span>'
            . $inner
            . '<div class="admin-stat-lines">' . $context . '</div>'
            . '</div>';
    }
}

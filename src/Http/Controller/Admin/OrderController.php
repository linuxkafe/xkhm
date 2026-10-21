<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;

/**
 * The orders: the list and the detail.
 */
class OrderController extends AdminController
{
    private const STATUSES = ['Active', 'Pending', 'Completed', 'Cancelled'];

    /**
     * GET /admin/orders.php
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
            return $this->badRequest($response, 'O pedido', $this->adminName());
        }

        $search = $this->searchTerm($request);
        $status = $this->status($request, self::STATUSES);
        $page = $this->page($request);

        $total = $this->repository->countOrders($status, $search);
        $orders = $this->repository->listOrders(
            $status,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'number' => sprintf(
                    '<a href="/admin/orders.php?id=%d">%s</a>',
                    (int)$order['id'],
                    $this->e($order['ordernum'])
                ),
                'client' => sprintf(
                    '<a href="/admin/clients.php?id=%d">#%d</a>',
                    (int)$order['userid'],
                    (int)$order['userid']
                ),
                'date' => $this->datetime($order['date'] ?? null),
                'total' => $this->money($order['total']),
                'invoice' => (int)($order['invoiceid'] ?? 0) > 0
                    ? sprintf(
                        '<a href="/admin/invoices.php?id=%d">#%d</a>',
                        (int)$order['invoiceid'],
                        (int)$order['invoiceid']
                    )
                    : '—',
                'status' => $this->badge((string)$order['status']),
            ];
        }

        $body = AdminLayout::header('Pedidos')
            . $this->filters('/admin/orders.php', $search, $status, self::STATUSES, 'Número do pedido')
            . $this->table(
                [
                    ['label' => 'Pedido', 'value' => 'number'],
                    ['label' => 'Cliente', 'value' => 'client'],
                    ['label' => 'Data', 'value' => 'date'],
                    ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                    ['label' => 'Fatura', 'value' => 'invoice'],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Nenhum pedido encontrado.'
            )
            . $this->pager($total, $page, array_filter([
                'q' => $search,
                'status' => $status,
            ]));

        return $this->render($response, 'Pedidos', 'orders', $body, $this->adminName());
    }

    /**
     * GET /admin/orders.php?id=N
     */
    public function detail(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O pedido', $admin);
        }

        $order = $this->repository->findOrder($id);

        if ($order === null) {
            return $this->notFound($response, 'O pedido', $admin);
        }

        $fields = [
            'Cliente' => sprintf(
                '<a href="/admin/clients.php?id=%d">#%d</a>',
                (int)$order['userid'],
                (int)$order['userid']
            ),
            'Data' => $this->datetime($order['date'] ?? null),
            'Total' => $this->money($order['total']),
            'Estado' => $this->badge((string)$order['status']),
            'Via de pagamento' => $this->e($order['paymentmethod'] ?? ''),
            'Fatura' => (int)($order['invoiceid'] ?? 0) > 0
                ? sprintf(
                    '<a href="/admin/invoices.php?id=%d">#%d</a>',
                    (int)$order['invoiceid'],
                    (int)$order['invoiceid']
                )
                : '—',
            'Endereço IP' => $this->e($order['ipaddress'] ?? ''),
        ];

        $body = AdminLayout::header(
            'Pedido ' . (string)$order['ordernum'],
            ['Pedidos' => '/admin/orders.php']
        );

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        foreach ($fields as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl></div></section>';

        if (($order['notes'] ?? '') !== '') {
            $body .= '<section class="panel"><div class="panel-heading">Notas</div>'
                . '<div class="panel-body"><p>' . nl2br($this->e((string)$order['notes'])) . '</p>'
                . '</div></section>';
        }

        return $this->render(
            $response,
            'Pedido ' . (string)$order['ordernum'],
            'orders',
            $body,
            $admin
        );
    }
}

<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;

/**
 * The invoices: the list and the detail.
 */
class InvoiceController extends AdminController
{
    private const STATUSES = ['Paid', 'Unpaid', 'Cancelled'];

    /**
     * GET /admin/invoices.php
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
            return $this->badRequest($response, 'A fatura', $this->adminName());
        }

        $search = $this->searchTerm($request);
        $status = $this->status($request, self::STATUSES);
        $page = $this->page($request);

        $total = $this->repository->countInvoices($status, $search);
        $invoices = $this->repository->listInvoices(
            $status,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($invoices as $invoice) {
            $rows[] = [
                'number' => sprintf(
                    '<a href="/admin/invoices.php?id=%d">%s</a>',
                    (int)$invoice['id'],
                    $this->e($this->invoiceNumber($invoice))
                ),
                'client' => sprintf(
                    '<a href="/admin/clients.php?id=%d">#%d</a>',
                    (int)$invoice['userid'],
                    (int)$invoice['userid']
                ),
                'date' => $this->date($invoice['datecreated'] ?? null),
                'due' => $this->date($invoice['duedate'] ?? null),
                'total' => $this->money($invoice['total']),
                'status' => $this->badge((string)$invoice['status']),
            ];
        }

        $body = AdminLayout::header('Faturas')
            . $this->filters('/admin/invoices.php', $search, $status, self::STATUSES, 'Número da fatura')
            . $this->table(
                [
                    ['label' => 'Fatura', 'value' => 'number'],
                    ['label' => 'Cliente', 'value' => 'client'],
                    ['label' => 'Data', 'value' => 'date'],
                    ['label' => 'Vencimento', 'value' => 'due'],
                    ['label' => 'Total', 'value' => 'total', 'numeric' => true],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Nenhuma fatura encontrada.'
            )
            . $this->pager($total, $page, array_filter([
                'q' => $search,
                'status' => $status,
            ]));

        return $this->render($response, 'Faturas', 'invoices', $body, $this->adminName());
    }

    /**
     * GET /admin/invoices.php?id=N
     */
    public function detail(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'A fatura', $admin);
        }

        $invoice = $this->repository->findInvoice($id);

        if ($invoice === null) {
            return $this->notFound($response, 'A fatura', $admin);
        }

        $items = $this->repository->invoiceItems($id);

        $body = AdminLayout::header(
            'Fatura ' . (string)$invoice['invoicenum'],
            ['Faturas' => '/admin/invoices.php']
        );

        $fields = [
            'Cliente' => sprintf(
                '<a href="/admin/clients.php?id=%d">#%d</a>',
                (int)$invoice['userid'],
                (int)$invoice['userid']
            ),
            'Data' => $this->date($invoice['datecreated'] ?? null),
            'Vencimento' => $this->date($invoice['duedate'] ?? null),
            'Paga em' => $this->datetime($invoice['datepaid'] ?? null),
            'Subtotal' => $this->money($invoice['subtotal'] ?? 0),
            'Imposto' => $this->money((float)($invoice['tax'] ?? 0) + (float)($invoice['tax2'] ?? 0)),
            'Crédito' => $this->money($invoice['credit'] ?? 0),
            'Total' => $this->money($invoice['total']),
            'Estado' => $this->badge((string)$invoice['status']),
            'Via de pagamento' => $this->e($invoice['paymentmethod'] ?? ''),
        ];

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        foreach ($fields as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl></div></section>';

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'description' => $this->e($item['description'] ?? ''),
                'type' => $this->e($item['type'] ?? ''),
                'amount' => $this->money($item['amount'] ?? 0),
            ];
        }

        $body .= '<section class="panel"><div class="panel-heading">Itens</div><div class="panel-body">'
            . $this->table(
                [
                    ['label' => 'Descrição', 'value' => 'description'],
                    ['label' => 'Tipo', 'value' => 'type'],
                    ['label' => 'Valor', 'value' => 'amount', 'numeric' => true],
                ],
                $rows,
                'Esta fatura não tem itens.'
            )
            . '</div></section>';

        if (($invoice['notes'] ?? '') !== '') {
            $body .= '<section class="panel"><div class="panel-heading">Notas</div>'
                . '<div class="panel-body"><p>' . nl2br($this->e((string)$invoice['notes'])) . '</p>'
                . '</div></section>';
        }

        return $this->render(
            $response,
            'Fatura ' . (string)$invoice['invoicenum'],
            'invoices',
            $body,
            $admin
        );
    }
}

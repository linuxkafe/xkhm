<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Repository\OrderRepositoryInterface;
use Xkhm\Repository\ClientRepositoryInterface;

class BillingService
{
    private InvoiceRepositoryInterface $invoiceRepository;
    private OrderRepositoryInterface $orderRepository;
    private ClientRepositoryInterface $clientRepository;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        ClientRepositoryInterface $clientRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->clientRepository = $clientRepository;
    }

    /**
     * Create an invoice from an order.
     */
    public function createInvoiceFromOrder(int $orderId): ?int
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            return null;
        }

        // Invoice data includes order reference and calculated totals
        $invoiceData = [
            'userid' => $order['userid'],
            'datecreated' => date('Y-m-d'),
            'duedate' => date('Y-m-d', strtotime('+7 days')),
            'status' => 'Unpaid',
            'total' => $order['total'] ?? 0,
            'currency' => $order['currency'] ?? '1',
            'taxrate' => $order['tax'] ?? 0,
            'note' => '',
        ];

        return $this->invoiceRepository->create($invoiceData);
    }

    /**
     * Get all invoices for a client.
     */
    public function getClientInvoices(int $clientId): array
    {
        return $this->invoiceRepository->findByUserId($clientId);
    }

    /**
     * Get a specific invoice with line items.
     */
    public function getInvoiceWithItems(int $invoiceId): ?array
    {
        return $this->invoiceRepository->findByIdWithItems($invoiceId);
    }

    /**
     * Get client's credit balance.
     */
    public function getClientCredit(int $clientId): float
    {
        $client = $this->clientRepository->findById($clientId);
        return (float)($client['credit'] ?? 0);
    }

    /**
     * Calculate total amount due for an invoice.
     */
    public function calculateTotal(int $invoiceId): float
    {
        $invoice = $this->getInvoiceWithItems($invoiceId);
        if (!$invoice) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($invoice['items'] ?? [] as $item) {
            $total += (float)($item['amount'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Check if an invoice is overdue.
     */
    public function isOverdue(int $invoiceId): bool
    {
        $invoice = $this->getInvoiceWithItems($invoiceId);
        if (!$invoice) {
            return false;
        }

        return $invoice['status'] === 'Unpaid' && strtotime($invoice['duedate']) < time();
    }

    /**
     * Get invoice summary for client dashboard.
     */
    public function getInvoiceSummary(int $clientId): array
    {
        $invoices = $this->getClientInvoices($clientId);
        $credits = $this->getClientCredit($clientId);

        $unpaidTotal = 0.0;
        $paidTotal = 0.0;
        $latestInvoiceId = null;

        foreach ($invoices as $invoice) {
            $status = $invoice['status'] ?? '';
            $amount = (float)($invoice['total'] ?? 0);

            if ($status === 'Paid') {
                $paidTotal += $amount;
            } elseif ($status === 'Unpaid') {
                $unpaidTotal += $amount;
                $latestInvoiceId = (int)$invoice['id'];
            }
        }

        return [
            'credit_balance' => $credits,
            'unpaid_total' => round($unpaidTotal, 2),
            'paid_total' => round($paidTotal, 2),
            'latest_invoice_id' => $latestInvoiceId,
            'invoice_count' => count($invoices),
        ];
    }
}

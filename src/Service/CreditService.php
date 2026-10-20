<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Repository\ClientRepositoryInterface;

class CreditService
{
    private InvoiceRepositoryInterface $invoiceRepository;
    private ClientRepositoryInterface $clientRepository;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        ClientRepositoryInterface $clientRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->clientRepository = $clientRepository;
    }

    /**
     * Apply credit to a client account.
     */
    public function addCredit(int $clientId, float $amount, string $description): bool
    {
        $client = $this->clientRepository->findById($clientId);
        if (!$client) {
            return false;
        }

        $newCredit = (float)($client['credit'] ?? 0) + $amount;

        return $this->clientRepository->update($clientId, [
            'credit' => round($newCredit, 2),
        ]);
    }

    /**
     * Deduct credit to pay an invoice.
     */
    public function applyCreditToInvoice(int $clientId, int $invoiceId): bool
    {
        $invoice = $this->invoiceRepository->findByIdWithItems($invoiceId);
        if (!$invoice) {
            return false;
        }

        $credit = $this->getClientCredit($clientId);
        $invoiceTotal = (float)($invoice['total'] ?? 0);

        if ($credit >= $invoiceTotal) {
            // Full credit application
            $this->invoiceRepository->update($invoiceId, [
                'status' => 'Paid',
                'datepaid' => date('Y-m-d'),
                'total' => 0,
            ]);
            $this->addCredit($clientId, -$invoiceTotal, 'Credit applied to invoice #' . $invoiceId);
            return true;
        }

        // Partial credit application
        $remaining = $invoiceTotal - $credit;
        $this->invoiceRepository->update($invoiceId, [
            'total' => $remaining,
        ]);
        $this->addCredit($clientId, -$credit, 'Partial credit applied to invoice #' . $invoiceId);
        return true;
    }

    /**
     * Get client credit balance.
     */
    public function getClientCredit(int $clientId): float
    {
        $client = $this->clientRepository->findById($clientId);
        return (float)($client['credit'] ?? 0);
    }

    /**
     * Get credit history for a client.
     */
    public function getCreditHistory(int $clientId): array
    {
        // TODO: Implement tblaccounts queries for full history
        return [];
    }
}

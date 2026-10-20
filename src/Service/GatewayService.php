<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Config;

class GatewayService
{
    private InvoiceRepositoryInterface $invoiceRepository;

    public function __construct(InvoiceRepositoryInterface $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Process payment for an invoice.
     * Returns [success, message, transaction_id]
     */
    public function processPayment(int $invoiceId, string $gateway, array $cardData): array
    {
        $invoice = $this->invoiceRepository->findByIdWithItems($invoiceId);
        if (!$invoice) {
            return [false, 'Invoice not found', null];
        }

        $amount = (float)($invoice['total'] ?? 0);
        if ($amount <= 0) {
            return [true, 'No amount due', null];
        }

        // Gateway-specific processing
        $result = match ($gateway) {
            'paypal' => $this->processPaypalPayment($amount, $cardData),
            'authorize' => $this->processAuthorizePayment($amount, $cardData),
            'worldpay' => $this->processWorldpayPayment($amount, $cardData),
            default => [false, 'Unknown gateway: ' . $gateway, null],
        };

        if ($result[0] && $result[1] !== null) {
            $this->invoiceRepository->update($invoiceId, [
                'status' => 'Paid',
                'datepaid' => date('Y-m-d'),
            ]);
        }

        return $result;
    }

    /**
     * Get list of active gateways.
     */
    public function getActiveGateways(): array
    {
        return [
            ['system' => 'paypal', 'friendlyname' => 'PayPal'],
            ['system' => 'authorize', 'friendlyname' => 'Authorize.Net'],
            ['system' => 'worldpay', 'friendlyname' => 'WorldPay'],
        ];
    }

    private function processPaypalPayment(float $amount, array $data): array
    {
        // PayPal sandbox integration (stub)
        return [true, 'PayPal payment processed', 'PP-' . time()];
    }

    private function processAuthorizePayment(float $amount, array $data): array
    {
        // Authorize.Net integration (stub)
        return [true, 'Authorize.Net payment processed', 'AN-' . time()];
    }

    private function processWorldpayPayment(float $amount, array $data): array
    {
        // WorldPay integration (stub)
        return [true, 'WorldPay payment processed', 'WP-' . time()];
    }
}

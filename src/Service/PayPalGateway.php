<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;

class PayPalGateway implements GatewayInterface
{
    private InvoiceRepositoryInterface $invoiceRepo;
    private array $config;

    public function __construct(InvoiceRepositoryInterface $invoiceRepo, array $config = [])
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function getFriendlyName(): string
    {
        return 'PayPal';
    }

    public function isTestMode(): bool
    {
        return (bool)($this->config['testmode'] ?? false);
    }

    public function processPayment(float $amount, array $data): array
    {
        // Build PayPal redirect URL
        $baseUrl = $this->isTestMode()
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $params = [
            'cmd' => '_xclick',
            'business' => $this->config['email'] ?? '',
            'amount' => number_format($amount, 2),
            'currency_code' => 'USD',
            'return' => $this->config['success_url'] ?? '',
            'cancel_return' => $this->config['cancel_url'] ?? '',
            'custom' => $data['invoice_id'] ?? '',
            'notify_url' => $this->config['ipn_url'] ?? '',
        ];

        $url = $baseUrl . '?' . http_build_query($params);

        // Return redirect URL (PayPal uses redirect, not direct processing)
        return ['redirect' => $url, 'status' => 'pending'];
    }

    public function handleCallback(array $callbackData): array
    {
        // Verify IPN
        $verified = $this->verifyIPN($callbackData);

        if (!$verified) {
            return ['success' => false, 'message' => 'IPN verification failed'];
        }

        $invoiceId = $callbackData['custom'] ?? '';
        $paymentStatus = $callbackData['payment_status'] ?? '';
        $transactionId = $callbackData['txn_id'] ?? '';

        if ($paymentStatus === 'Completed') {
            // Update invoice status
            $this->invoiceRepo->update((int)$invoiceId, [
                'status' => 'Paid',
                'datepaid' => date('Y-m-d'),
            ]);

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
                'amount' => (float)($callbackData['mc_gross'] ?? 0),
            ];
        }

        return ['success' => false, 'message' => 'Payment not completed'];
    }

    public function getConfiguration(): array
    {
        return $this->config;
    }

    public function setConfiguration(array $config): bool
    {
        $this->config = array_merge($this->config, $config);
        return true;
    }

    private function verifyIPN(array $data): bool
    {
        // In production, send back to PayPal for verification
        // For now, return true (simplified for initial implementation)
        return true;
    }
}

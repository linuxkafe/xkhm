<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;

class AuthorizeNetGateway implements GatewayInterface
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
        return 'authorize';
    }

    public function getFriendlyName(): string
    {
        return 'Authorize.Net';
    }

    public function isTestMode(): bool
    {
        return (bool)($this->config['testmode'] ?? true);
    }

    public function processPayment(float $amount, array $data): array
    {
        // Authorize.Net AIM integration
        $apiLogin = $this->config['api_login'] ?? '';
        $transactionKey = $this->config['transaction_key'] ?? '';

        if (empty($apiLogin) || empty($transactionKey)) {
            return ['success' => false, 'message' => 'Gateway not configured'];
        }

        // Build AIM request
        $params = [
            'x_login' => $apiLogin,
            'x_tran_key' => $transactionKey,
            'x_version' => '3.1',
            'x_delim_data' => 'TRUE',
            'x_delim_char' => '|',
            'x_relay_response' => 'FALSE',
            'x_type' => 'AUTH_CAPTURE',
            'x_amount' => number_format($amount, 2),
            'x_card_num' => $data['cc_number'] ?? '',
            'x_exp_date' => $data['cc_expiry'] ?? '',
            'x_invoice_num' => $data['invoice_id'] ?? '',
            'x_description' => 'Invoice #' . ($data['invoice_id'] ?? ''),
        ];

        // In production, this would make an HTTP POST to Authorize.Net
        // For now, return simulated success
        return [
            'success' => true,
            'transaction_id' => 'AN-' . time(),
            'response_code' => 1,
            'response_reason' => 'Approved',
        ];
    }

    public function handleCallback(array $callbackData): array
    {
        // SIM relay response handling
        $responseCode = $callbackData['x_response_code'] ?? 0;

        if ($responseCode == 1) {
            return [
                'success' => true,
                'transaction_id' => $callbackData['x_trans_id'] ?? '',
                'amount' => (float)($callbackData['x_amount'] ?? 0),
            ];
        }

        return ['success' => false, 'message' => 'Transaction declined'];
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
}
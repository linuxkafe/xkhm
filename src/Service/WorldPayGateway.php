<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;

class WorldPayGateway implements GatewayInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'worldpay';
    }

    public function getFriendlyName(): string
    {
        return 'WorldPay';
    }

    public function isTestMode(): bool
    {
        return (bool)($this->config['testmode'] ?? true);
    }

    public function processPayment(float $amount, array $data): array
    {
        // WorldPay hosted payment page redirect
        $merchantId = $this->config['merchant_id'] ?? '';
        $currencyCode = $this->config['currency'] ?? 'USD';

        if (empty($merchantId)) {
            return ['success' => false, 'message' => 'Gateway not configured'];
        }

        // Build WorldPay checkout URL
        $baseUrl = $this->isTestMode()
            ? 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentShop.jsp'
            : 'https://secure.worldpay.com/jsp/merchant/xml/paymentShop.jsp';

        $params = [
            'command' => 'testSale',
            'cat' => $merchantId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currencyCode,
            'desc' => 'Invoice #' . ($data['invoice_id'] ?? ''),
            'success' => $this->config['success_url'] ?? '',
            'cancel' => $this->config['cancel_url'] ?? '',
            'decline' => $this->config['cancel_url'] ?? '',
            'icon' => $this->config['icon_url'] ?? '',
        ];

        $url = $baseUrl . '?' . http_build_query($params);

        return ['redirect' => $url, 'status' => 'pending'];
    }

    public function handleCallback(array $callbackData): array
    {
        // WorldPay asynchronous notification handling
        $authCode = $callbackData['authcode'] ?? '';
        $authMessage = $callbackData['authmessage'] ?? '';

        if ($authCode !== '00') {
            return ['success' => false, 'message' => 'Payment declined: ' . $authMessage];
        }

        return [
            'success' => true,
            'transaction_id' => $callbackData['transid'] ?? '',
            'amount' => (float)($callbackData['total'] ?? 0),
        ];
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

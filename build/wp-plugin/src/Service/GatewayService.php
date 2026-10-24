<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\InvoiceRepositoryInterface;

class GatewayService
{
    private InvoiceRepositoryInterface $invoiceRepo;
    /** @var GatewayInterface[] */
    private array $gateways = [];

    public function __construct(InvoiceRepositoryInterface $invoiceRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->registerGateways();
    }

    /**
     * Register all available gateways.
     */
    private function registerGateways(): void
    {
        $this->gateways['paypal'] = new PayPalGateway($this->invoiceRepo);
        $this->gateways['authorize'] = new AuthorizeNetGateway();
        $this->gateways['worldpay'] = new WorldPayGateway();
    }

    /**
     * Get all available gateways.
     * @return GatewayInterface[]
     */
    public function getGateways(): array
    {
        return $this->gateways;
    }

    /**
     * Get active gateway configurations.
     */
    public function getActiveGateways(): array
    {
        $result = [];
        foreach ($this->gateways as $name => $gateway) {
            $result[] = [
                'system' => $name,
                'friendlyname' => $gateway->getFriendlyName(),
                'testmode' => $gateway->isTestMode(),
            ];
        }
        return $result;
    }

    /**
     * Process payment through specified gateway.
     * Returns [success, message, transaction_id]
     */
    public function processPayment(int $invoiceId, string $gateway, array $cardData): array
    {
        if (!isset($this->gateways[$gateway])) {
            return [false, 'Unknown gateway: ' . $gateway, null];
        }

        $gw = $this->gateways[$gateway];
        $result = $gw->processPayment(
            $this->invoiceRepo->getInvoiceTotal($invoiceId) ?? 0,
            array_merge($cardData, ['invoice_id' => $invoiceId])
        );

        // If redirect (PayPal, WorldPay), return the URL
        if (isset($result['redirect'])) {
            return [true, 'Redirect to payment page', $result['redirect']];
        }

        // If direct processing (Authorize.Net), update invoice
        if ($result['success'] ?? false) {
            $this->invoiceRepo->update($invoiceId, [
                'status' => 'Paid',
                'datepaid' => date('Y-m-d'),
            ]);
            return [true, 'Payment processed', $result['transaction_id'] ?? null];
        }

        return [false, $result['message'] ?? 'Payment failed', null];
    }

    /**
     * Handle gateway callback/webhook.
     */
    public function handleCallback(string $gateway, array $callbackData): array
    {
        if (!isset($this->gateways[$gateway])) {
            return ['success' => false, 'message' => 'Unknown gateway'];
        }

        return $this->gateways[$gateway]->handleCallback($callbackData);
    }
}

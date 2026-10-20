<?php

declare(strict_types=1);

namespace Xkhm\Service;

interface GatewayInterface
{
    public function getName(): string;
    public function getFriendlyName(): string;
    public function isTestMode(): bool;
    public function processPayment(float $amount, array $data): array;
    public function handleCallback(array $callbackData): array;
    public function getConfiguration(): array;
    public function setConfiguration(array $config): bool;
}

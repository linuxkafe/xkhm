<?php

declare(strict_types=1);

namespace Xkhm\Service;

interface GatewayInterface
{
    public function getName(): string;
    public function getFriendlyName(): string;
    public function processPayment(array $data): array;
}

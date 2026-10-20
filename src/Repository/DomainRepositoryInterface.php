<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface DomainRepositoryInterface
{
    public function checkAvailability(string $domain, array $tlds): array;
    public function register(int $userId, string $domain, int $period): int;
    public function findByUserId(int $userId): array;
    public function getPricing(array $tlds): array;
}

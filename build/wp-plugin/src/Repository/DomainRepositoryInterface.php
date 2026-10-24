<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface DomainRepositoryInterface
{
    public function checkAvailability(string $domain, array $tlds): array;
    public function register(int $userId, string $domain, int $period): int;
    public function findByUserId(int $userId): array;
    public function getPricing(array $tlds): array;

    // Additional methods used by DomainService
    public function findByName(string $domain): ?array;
    public function findById(int $id): ?array;
    public function findByIdWithDns(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    /**
     * The prices of the domains, by TLD.
     *
     * @return array<string, array{register: float, transfer: float, renew: float, minYears: int}>
     */
    public function getTLDPrices(): array;
}

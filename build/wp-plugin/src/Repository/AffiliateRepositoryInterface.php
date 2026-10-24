<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface AffiliateRepositoryInterface
{
    public function create(array $data): int;
    public function findByUserId(int $userId): ?array;
    public function findById(int $id): ?array;
    public function findByRefCode(string $code): ?array;
    public function addReferral(array $data): int;
    public function getReferralsByAffiliate(int $affiliateId): array;
    public function getEarningsByAffiliate(int $affiliateId): float;
    public function getPayoutHistory(int $affiliateId): array;
}

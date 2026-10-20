<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface AffiliateRepositoryInterface
{
    public function findByUserId(int $userId): ?array;
    public function create(int $userId): int;
    public function getCommissions(int $userId): array;
    public function getWithdrawals(int $userId): array;
}

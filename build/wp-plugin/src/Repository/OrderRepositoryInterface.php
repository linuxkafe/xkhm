<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByUserId(int $userId): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
}

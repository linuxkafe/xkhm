<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByUserId(int $userId): array;
    public function findByIdWithItems(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function getInvoiceTotal(int $id): ?float;
}

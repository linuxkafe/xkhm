<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByUserId(int $userId): array;
    public function findByIdWithItems(int $id): ?array;
    public function create(array $data): int;

    /**
     * Add a line to an invoice.
     *
     * @param array<string, mixed> $data
     */
    public function addItem(int $invoiceId, array $data): int;
    public function update(int $id, array $data): bool;
    public function getInvoiceTotal(int $id): ?float;
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

/**
 * The services — the rows of `tblhosting`.
 *
 * `tblhosting` holds **every** service of the installation, not only
 * the web hosting: the backup, the assistance, the virtual servers.
 * The column that says which one is `packageid`, and it points at
 * `tblproducts`.
 */
interface ServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * The services of a client.
     *
     * @return list<array<string, mixed>>
     */
    public function findByUserId(int $userId): array;

    /**
     * The services whose renewal invoice is due.
     *
     * "Due" means: the `nextinvoicedate` is within `$daysBefore` days
     * of `$date` — the WHMCS's `CreateInvoiceDaysBefore`.
     *
     * @return list<array<string, mixed>>
     */
    public function dueForInvoice(string $date, int $daysBefore): array;

    /**
     * The services whose invoice is overdue.
     *
     * @return list<array<string, mixed>>
     */
    public function overdue(string $date, int $days): array;

    /**
     * Create a service.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int;

    /**
     * Change a service.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    /**
     * The invoices already raised for a service, for a period.
     *
     * The guard that makes the billing cycle **idempotent**: a second
     * run must not raise a second invoice for the same period. The
     * period is the **due date** of the invoice — the day the service
     * renews.
     */
    public function invoicedForPeriod(int $serviceId, string $dueDate): int;
}

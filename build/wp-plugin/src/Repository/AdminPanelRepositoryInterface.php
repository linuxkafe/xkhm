<?php

declare(strict_types=1);

namespace Xkhm\Repository;

/**
 * The read queries of the administration panel.
 *
 * The panel is a **read** surface: the lists, the searches and the
 * dashboard aggregates. The operations that change something (replying
 * to a ticket, giving credit) go through the services that already
 * exist, never through a direct write here.
 *
 * Every method returns the data in the **canonical** shape — the same
 * one the client area uses, with {@see SchemaMap} as the single source
 * of truth about the schema.
 *
 * This is not {@see AdminRepositoryInterface}: that one is the
 * administrator's **own** row (the authentication), this one is
 * everything the administrator looks at.
 */
interface AdminPanelRepositoryInterface
{
    // ─── Dashboard ──────────────────────────────────────────────────────────

    /**
     * The numbers of the dashboard.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array;

    // ─── Clients ────────────────────────────────────────────────────────────

    public function countClients(?string $search = null): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listClients(?string $search, int $limit, int $offset): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findClient(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function clientInvoices(int $clientId, int $limit = 25): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function clientOrders(int $clientId, int $limit = 25): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function clientDomains(int $clientId, int $limit = 25): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function clientTickets(int $clientId, int $limit = 25): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function clientTransactions(int $clientId, int $limit = 25): array;

    /**
     * The services of a client, with the product and the group.
     *
     * `tblhosting` holds **every** service, not only the web hosting:
     * what tells a backup from a hosting is the product it belongs to.
     *
     * @return list<array<string, mixed>>
     */
    public function clientServices(int $clientId): array;

    /**
     * Billing totals for a client.
     *
     * @return array{total: float, year: float}
     */
    public function clientBillingTotals(int $clientId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findService(int $id): ?array;

    /**
     * The price of a product, by cycle.
     *
     * The WHMCS keeps a price per billing cycle; `-1.00` means the
     * product does not have that cycle.
     *
     * @return array<string, float>
     */
    public function productPrices(int $packageId): array;

    /**
     * Change the price and the renewal term of a service.
     */
    public function updateServiceTerms(
        int $id,
        float $amount,
        string $billingCycle,
        string $nextDueDate
    ): bool;

    /**
     * Write a note on the client — as the WHMCS does.
     */
    public function addNote(int $clientId, int $adminId, string $note): bool;

    // ─── Invoices ───────────────────────────────────────────────────────────

    public function countInvoices(?string $status = null, ?string $search = null): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listInvoices(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findInvoice(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function invoiceItems(int $invoiceId): array;

    // ─── Orders ─────────────────────────────────────────────────────────────

    public function countOrders(?string $status = null, ?string $search = null): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findOrder(int $id): ?array;

    // ─── Tickets ────────────────────────────────────────────────────────────

    public function countTickets(?string $status = null, ?string $search = null): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listTickets(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findTicket(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function ticketReplies(int $ticketId): array;

    // ─── Domains ────────────────────────────────────────────────────────────

    public function countDomains(?string $search = null): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listDomains(?string $search, int $limit, int $offset): array;

    /**
     * Distinct registrars for the dropdown.
     *
     * @return list<string>
     */
    public function getRegistrars(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findDomain(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function updateDomain(int $id, array $data): bool;

    // ─── Affiliates ─────────────────────────────────────────────────────────

    public function countAffiliates(): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listAffiliates(int $limit, int $offset): array;

    // ─── The global search ──────────────────────────────────────────────────

    /**
     * Search across the entities an administrator looks for.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function globalSearch(string $term, int $limit = 10): array;

    // ─── The configuration ──────────────────────────────────────────────────

    /**
     * @return list<array{setting: string, value: string}>
     */
    public function configuration(string $prefix = ''): array;

    public function configurationValue(string $setting): ?string;
}

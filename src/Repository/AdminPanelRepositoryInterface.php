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
     * @return list<array<string, mixed>>
     */
    public function clientHosting(int $clientId): array;

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

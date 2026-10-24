<?php

declare(strict_types=1);

namespace Xkhm\Repository;

/**
 * The catalogue: the products (the services on sale), their prices,
 * the product groups, and the servers.
 *
 * These are the tables the WHMCS's configuration pages `products`,
 * `servers` and `clientgroups` manage — and the ones the operator
 * needs to be able to manage from the panel.
 */
interface CatalogueRepositoryInterface
{
    // ─── The products ───────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function products(?int $groupId = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function product(int $id): ?array;

    /**
     * The prices of a product, by cycle.
     *
     * @return array<string, float>
     */
    public function productPrices(int $productId): array;

    /**
     * Create or change a product.
     *
     * @param array<string, mixed> $data
     */
    public function saveProduct(array $data, int $id = 0): int;

    /**
     * The prices of a product, by cycle.
     *
     * @param array<string, float> $prices
     */
    public function saveProductPrices(int $productId, array $prices): bool;

    /**
     * Remove a product, and the prices that belong to it.
     */
    public function deleteProduct(int $id): bool;

    /**
     * The number of services that belong to a product.
     *
     * A product that a client already has cannot be removed: the
     * service would be left without a product. The panel says so
     * instead of removing it.
     */
    public function servicesOfProduct(int $productId): int;

    // ─── The groups ─────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function productGroups(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function serverGroups(): array;

    /**
     * Create or change a product group.
     *
     * @param array<string, mixed> $data
     */
    public function saveProductGroup(array $data, int $id = 0): int;

    /**
     * Create or change a server group.
     *
     * @param array<string, mixed> $data
     */
    public function saveServerGroup(array $data, int $id = 0): int;

    // ─── The servers ────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function servers(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function server(int $id): ?array;

    /**
     * Create or change a server.
     *
     * @param array<string, mixed> $data
     */
    public function saveServer(array $data, int $id = 0): int;

    /**
     * The servers of a group.
     *
     * @return list<array<string, mixed>>
     */
    public function serversOfGroup(int $groupId): array;

    /**
     * Put a server in a group, or take it out.
     */
    public function setServerInGroup(int $groupId, int $serverId, bool $member): bool;

    /**
     * The groups a server belongs to.
     *
     * @return list<int>
     */
    public function groupsOfServer(int $serverId): array;

    /**
     * Remove a server, and the groups it belonged to.
     */
    public function deleteServer(int $id): bool;
}

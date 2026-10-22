<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Repository\CatalogueRepositoryPDO;

/**
 * The catalogue: the products, their prices, and the servers.
 *
 * These are the tables the WHMCS's `configproducts` and `configservers`
 * pages manage, and the ones that decide what a client can buy and
 * where it is hosted.
 */
class CatalogueRepositoryTest extends TestCase
{
    private ?CatalogueRepositoryPDO $catalogue = null;

    protected function setUp(): void
    {
        try {
            $this->catalogue = new CatalogueRepositoryPDO();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public function testTheProductsComeFromTheTable(): void
    {
        $products = $this->catalogue->products();

        $this->assertGreaterThan(10, count($products), 'the WHMCS had 24 products');

        $names = array_column($products, 'name');
        $this->assertContains('LINUXKONECT XK2', $names);
    }

    public function testAProductHasItsTypeAndGroup(): void
    {
        $product = $this->catalogue->product(1);

        $this->assertNotNull($product);
        $this->assertSame('hostingaccount', $product['type']);
        $this->assertSame('Web Hosting', $product['groupname']);
    }

    public function testThePricesAreByCycle(): void
    {
        $product = $this->catalogue->product(1);
        $prices = $product['prices'] ?? [];

        // O produto tem de ter um preço anual.
        $this->assertArrayHasKey('annually', $prices);
        $this->assertGreaterThan(0, $prices['annually']);
    }

    public function testACycleTheProductDoesNotHaveIsMinusOne(): void
    {
        // O `tblpricing` marca os ciclos que o produto não tem com
        // `-1.00` — o modo do WHMCS.
        $products = $this->catalogue->products();

        $found = false;
        foreach ($products as $product) {
            foreach ($product['prices'] ?? [] as $cycle => $price) {
                if ($cycle !== 'setup' && (float)$price === -1.0) {
                    $found = true;
                }
            }
        }

        $this->assertTrue($found, 'some cycles are not offered');
    }

    public function testTheProductGroupsAreListed(): void
    {
        $groups = $this->catalogue->productGroups();

        $this->assertGreaterThan(3, count($groups));

        $names = array_column($groups, 'name');
        $this->assertContains('Web Hosting', $names);
    }

    public function testTheServersAreListed(): void
    {
        $servers = $this->catalogue->servers();

        $this->assertGreaterThanOrEqual(1, count($servers));

        $server = $servers[0];
        $this->assertArrayHasKey('hostname', $server);
        $this->assertArrayHasKey('ipaddress', $server);
    }

    public function testTheServerGroupsAreListed(): void
    {
        $groups = $this->catalogue->serverGroups();

        $this->assertGreaterThanOrEqual(1, count($groups));
    }

    public function testAServerKnowsItsGroups(): void
    {
        $servers = $this->catalogue->servers();

        foreach ($servers as $server) {
            $this->assertArrayHasKey('groups', $server);
            $this->assertIsArray($server['groups']);
        }
    }

    public function testTheServersOfAGroupAreTheOnesAssignedToIt(): void
    {
        $groups = $this->catalogue->serverGroups();

        if ($groups === []) {
            $this->markTestSkipped('no server groups in this installation');
        }

        $groupId = (int)$groups[0]['id'];
        $servers = $this->catalogue->serversOfGroup($groupId);

        // Cada servidor do grupo pertence mesmo ao grupo.
        foreach ($servers as $server) {
            $this->assertContains($groupId, $this->catalogue->groupsOfServer((int)$server['id']));
        }

        // E nenhum servidor de fora do grupo aparece na lista.
        foreach ($this->catalogue->servers() as $server) {
            $inGroup = in_array($groupId, $this->catalogue->groupsOfServer((int)$server['id']), true);

            if (!$inGroup) {
                $this->assertNotContains(
                    (int)$server['id'],
                    array_map(fn (array $s): int => (int)$s['id'], $servers)
                );
            }
        }
    }
}

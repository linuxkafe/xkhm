<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\CatalogueRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryPDO;
use Xkhm\Repository\OrderRepositoryPDO;
use Xkhm\Repository\ServiceRepositoryPDO;
use Xkhm\Repository\SettingsRepositoryPDO;
use Xkhm\Service\InvoiceNumberService;
use Xkhm\Service\OrderService;
use Xkhm\Service\SettingsService;

/**
 * The order: from the catalogue to the service.
 *
 * The tests use their own client and clean up after themselves: the
 * installation's data is never touched.
 */
class OrderServiceTest extends TestCase
{
    private ?OrderService $orders = null;
    private ?\PDO $pdo = null;

    /** @var list<int> */
    private array $orderIds = [];

    /** @var list<int> */
    private array $invoiceIds = [];

    /** @var list<int> */
    private array $serviceIds = [];

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $settingsRepository = new SettingsRepositoryPDO();

        $this->orders = new OrderService(
            new CatalogueRepositoryPDO($this->pdo),
            new OrderRepositoryPDO($this->pdo),
            new InvoiceRepositoryPDO($this->pdo),
            new ServiceRepositoryPDO($this->pdo),
            new InvoiceNumberService($settingsRepository, $this->pdo),
            new SettingsService($settingsRepository)
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->serviceIds as $id) {
            $this->pdo?->prepare('DELETE FROM tblhosting WHERE id = ?')->execute([$id]);
        }

        foreach ($this->invoiceIds as $id) {
            $this->pdo?->prepare('DELETE FROM tblinvoiceitems WHERE invoiceid = ?')->execute([$id]);
            $this->pdo?->prepare('DELETE FROM tblinvoices WHERE id = ?')->execute([$id]);
        }

        foreach ($this->orderIds as $id) {
            $this->pdo?->prepare('DELETE FROM tblorders WHERE id = ?')->execute([$id]);
        }
    }

    /**
     * The client's id — the development account.
     */
    private function clientId(): int
    {
        $stmt = $this->pdo?->prepare('SELECT id FROM tblclients WHERE email = ?');
        $stmt?->execute(['xkhm-test@example.com']);

        return (int)($stmt?->fetchColumn() ?: 0);
    }

    public function testTheCatalogueHasTheProductsAndTheirPrices(): void
    {
        $catalogue = $this->orders?->catalogue() ?? [];

        $this->assertGreaterThan(10, count($catalogue));

        $first = $catalogue[0];

        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('group', $first);
        $this->assertArrayHasKey('cycles', $first);
        $this->assertNotEmpty($first['cycles']);

        // Todo o produto do catalogo tem pelo menos um ciclo com preco.
        foreach ($catalogue as $product) {
            $this->assertNotEmpty($product['cycles'], $product['name']);

            foreach ($product['cycles'] as $cycle) {
                $this->assertGreaterThanOrEqual(0, $cycle['price']);
            }
        }
    }

    public function testTheCatalogueHidesTheHiddenProducts(): void
    {
        $catalogue = $this->orders?->catalogue() ?? [];
        $names = array_column($catalogue, 'name');

        // O produto escondido da instalacao nao aparece.
        $this->assertNotContains('Produto escondido de teste', $names);
    }

    public function testAnOrderCreatesTheOrderAndTheInvoice(): void
    {
        $clientId = $this->clientId();

        $this->assertGreaterThan(0, $clientId, 'the development client must exist');

        // Um produto de alojamento: o produto 1 e' o `LINUXKONECT XK2`.
        $result = $this->orders?->place($clientId, [
            'productId' => 1,
            'cycle' => 'Annually',
            'domain' => 'encomenda-teste.example.com',
        ]);

        $this->assertTrue($result['ok'], (string)($result['error'] ?? ''));

        $orderId = (int)$result['orderId'];
        $invoiceId = (int)$result['invoiceId'];

        $this->orderIds[] = $orderId;
        $this->invoiceIds[] = $invoiceId;

        // O pedido.
        $stmt = $this->pdo?->prepare('SELECT * FROM tblorders WHERE id = ?');
        $stmt?->execute([$orderId]);
        $order = $stmt?->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Pending', $order['status']);
        $this->assertSame($invoiceId, (int)$order['invoiceid']);
        $this->assertSame(25.0, (float)$order['amount'], 'the annual price of the product');

        // A fatura.
        $stmt = $this->pdo?->prepare('SELECT * FROM tblinvoices WHERE id = ?');
        $stmt?->execute([$invoiceId]);
        $invoice = $stmt?->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Unpaid', $invoice['status']);
        $this->assertSame(25.0, (float)$invoice['total']);
        $this->assertNotSame('', (string)$invoice['invoicenum']);

        // E o item, que aponta para o **produto** (o servico ainda nao
        // existe).
        $stmt = $this->pdo?->prepare('SELECT * FROM tblinvoiceitems WHERE invoiceid = ?');
        $stmt?->execute([$invoiceId]);
        $item = $stmt?->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Product', $item['type']);
        $this->assertSame(1, (int)$item['relid']);
        $this->assertStringContainsString('encomenda-teste.example.com', (string)$item['description']);
    }

    public function testAnOrderWithoutADomainIsRefused(): void
    {
        $result = $this->orders?->place($this->clientId(), [
            'productId' => 1,
            'cycle' => 'Annually',
            'domain' => '',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('domínio', (string)$result['error']);
    }

    public function testAnOrderForAnUnknownProductIsRefused(): void
    {
        $result = $this->orders?->place($this->clientId(), [
            'productId' => 999999,
            'cycle' => 'Annually',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function testAnOrderForACycleTheProductDoesNotHaveIsRefused(): void
    {
        // O produto 1 tem `Monthly` e `Annually`, mas nao `Biennially`.
        $result = $this->orders?->place($this->clientId(), [
            'productId' => 1,
            'cycle' => 'Biennially',
            'domain' => 'encomenda-teste.example.com',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ciclo', (string)$result['error']);
    }

    public function testPayingTheInvoiceCreatesTheService(): void
    {
        $clientId = $this->clientId();

        $result = $this->orders?->place($clientId, [
            'productId' => 1,
            'cycle' => 'Annually',
            'domain' => 'servico-teste.example.com',
        ]);

        $this->assertTrue($result['ok']);

        $orderId = (int)$result['orderId'];
        $invoiceId = (int)$result['invoiceId'];

        $this->orderIds[] = $orderId;
        $this->invoiceIds[] = $invoiceId;

        // O serviço **nao** existe antes do pagamento.
        $before = count($this->servicesOfOrder($orderId));
        $this->assertSame(0, $before);

        $this->orders?->markPaid($invoiceId, 'banktransfer');

        // E passa a existir, com o preco pago e o ciclo escolhido.
        $services = $this->servicesOfOrder($orderId);

        $this->assertCount(1, $services);

        $service = $services[0];
        $this->serviceIds[] = (int)$service['id'];

        $this->assertSame('servico-teste.example.com', $service['domain']);
        $this->assertSame('Annually', $service['billingcycle']);
        $this->assertSame(25.0, (float)$service['amount'], 'the price that was paid');
        $this->assertSame('Active', $service['domainstatus']);
        $this->assertSame(1, (int)$service['packageid']);

        // A data da renovacao e' um ano a partir de hoje.
        $expected = date('Y-m-d', strtotime('+1 year'));
        $this->assertSame($expected, $service['nextduedate']);

        // E a fatura esta paga.
        $stmt = $this->pdo?->prepare('SELECT status, datepaid FROM tblinvoices WHERE id = ?');
        $stmt?->execute([$invoiceId]);
        $invoice = $stmt?->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Paid', $invoice['status']);
        $this->assertNotSame('0000-00-00 00:00:00', (string)$invoice['datepaid']);
    }

    public function testPayingTwiceDoesNotCreateTwoServices(): void
    {
        $clientId = $this->clientId();

        $result = $this->orders?->place($clientId, [
            'productId' => 1,
            'cycle' => 'Annually',
            'domain' => 'servico-teste.example.com',
        ]);

        $this->assertTrue($result['ok']);

        $orderId = (int)$result['orderId'];
        $invoiceId = (int)$result['invoiceId'];

        $this->orderIds[] = $orderId;
        $this->invoiceIds[] = $invoiceId;

        $this->orders?->markPaid($invoiceId);
        $this->orders?->markPaid($invoiceId);

        $services = $this->servicesOfOrder($orderId);

        $this->assertCount(1, $services);
        $this->serviceIds[] = (int)$services[0]['id'];
    }

    /**
     * The services of an order.
     *
     * @return list<array<string, mixed>>
     */
    private function servicesOfOrder(int $orderId): array
    {
        $stmt = $this->pdo?->prepare('SELECT * FROM tblhosting WHERE orderid = ?');
        $stmt?->execute([$orderId]);

        return $stmt?->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}

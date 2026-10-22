<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\AdminPanelRepositoryPDO;
use Xkhm\Repository\ClientRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryPDO;
use Xkhm\Service\CreditService;
use Xkhm\Service\ServiceTermsService;

/**
 * The price and the renewal term of a service.
 *
 * The rule that matters is the **proration**: changing the price half
 * way through the period owes the customer the difference, for the
 * part of the period that is left.
 */
class ServiceTermsServiceTest extends TestCase
{
    private ?\PDO $pdo = null;
    private AdminPanelRepositoryPDO $repository;
    private ServiceTermsService $service;

    /** The service of the development client, and its state. */
    private int $serviceId = 0;
    private float $originalPrice = 0.0;
    private string $originalCycle = '';
    private string $originalDue = '';

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->repository = new AdminPanelRepositoryPDO($this->pdo);

        $clients = new ClientRepositoryPDO($this->pdo);
        $client = $clients->findByEmail('xkhm-test@example.com');

        if ($client === null) {
            $this->markTestSkipped('the development client does not exist');
        }

        $services = $this->repository->clientServices((int)$client['id']);

        if ($services === []) {
            $this->markTestSkipped('the development client has no services');
        }

        $row = $services[0];
        $this->serviceId = (int)$row['id'];
        $this->originalPrice = (float)$row['amount'];
        $this->originalCycle = (string)$row['billingcycle'];
        $this->originalDue = (string)$row['nextduedate'];
        $this->service = new ServiceTermsService(
            $this->repository,
            new CreditService(new InvoiceRepositoryPDO($this->pdo), $clients)
        );
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null || $this->serviceId === 0) {
            return;
        }

        $this->pdo->prepare(
            'UPDATE tblhosting SET amount = ?, billingcycle = ?, nextduedate = ? WHERE id = ?'
        )->execute([
            $this->originalPrice,
            $this->originalCycle,
            $this->originalDue,
            $this->serviceId,
        ]);

        $client = (new ClientRepositoryPDO($this->pdo))->findByEmail('xkhm-test@example.com');

        if ($client !== null) {
            $this->pdo->prepare('UPDATE tblclients SET credit = ? WHERE id = ?')
                ->execute([0.0, (int)$client['id']]);
        }

        $this->pdo->prepare('DELETE FROM tblnotes WHERE note LIKE ?')
            ->execute(['%serviço #' . $this->serviceId . '%']);
    }

    public function testTheCyclesComeFromTheProduct(): void
    {
        $service = $this->repository->findService($this->serviceId);
        $cycles = $this->service->availableCycles((int)$service['packageid']);

        $this->assertNotEmpty($cycles, 'the product has prices by cycle');

        foreach ($cycles as $cycle => $price) {
            $this->assertGreaterThanOrEqual(0, $price, 'a cycle without price is not offered');
            $this->assertArrayHasKey($cycle, ServiceTermsService::CYCLE_DAYS);
        }
    }

    public function testAQuoteDoesNotChangeAnything(): void
    {
        $before = $this->repository->findService($this->serviceId);

        $quote = $this->service->quote($this->serviceId, 'Monthly');

        $this->assertNotNull($quote);

        $after = $this->repository->findService($this->serviceId);

        $this->assertSame(
            (float)$before['amount'],
            (float)$after['amount'],
            'a quote changes nothing'
        );
        $this->assertSame($before['billingcycle'], $after['billingcycle']);
    }

    public function testTheQuoteBringsThePriceOfTheNewCycle(): void
    {
        $service = $this->repository->findService($this->serviceId);
        $cycles = $this->service->availableCycles((int)$service['packageid']);

        $cycle = array_key_first($cycles);
        $price = $cycles[$cycle];

        $quote = $this->service->quote($this->serviceId, (string)$cycle);

        $this->assertNotNull($quote);
        $this->assertSame($price, (float)$quote['newPrice'], 'the price comes from the product');
    }

    public function testTheProrationIsProportionalToTheTimeLeft(): void
    {
        // A price that is cut in half, with half the period gone.
        $this->pdo->prepare(
            'UPDATE tblhosting SET amount = ?, billingcycle = ?, nextduedate = ? WHERE id = ?'
        )->execute([
            100.0,
            'Annually',
            date('Y-m-d', strtotime('+182 days')),
            $this->serviceId,
        ]);

        $quote = $this->service->quote($this->serviceId, 'Annually', 50.0);

        $this->assertNotNull($quote);
        $this->assertSame(100.0, (float)$quote['currentPrice']);
        $this->assertSame(50.0, (float)$quote['newPrice']);

        // Half the price, for half the period that is left.
        $this->assertEqualsWithDelta(0.5, (float)$quote['fraction'], 0.02);
        $this->assertEqualsWithDelta(-25.0, (float)$quote['difference'], 1.0);
        $this->assertEqualsWithDelta(25.0, (float)$quote['credit'], 1.0);
    }

    public function testAFullPeriodHasNoProration(): void
    {
        // The renewal is due tomorrow: nothing of the period is left.
        $this->pdo->prepare(
            'UPDATE tblhosting SET amount = ?, nextduedate = ? WHERE id = ?'
        )->execute([100.0, date('Y-m-d', strtotime('+1 day')), $this->serviceId]);

        $quote = $this->service->quote($this->serviceId, 'Annually', 50.0);

        $this->assertNotNull($quote);
        $this->assertEqualsWithDelta(0.0, (float)$quote['difference'], 0.01);
        $this->assertSame(0.0, (float)$quote['credit']);
    }

    public function testTheChangeIsAppliedAndRecorded(): void
    {
        $current = (float)($this->repository->findService($this->serviceId)['amount'] ?? 0);

        $applied = $this->service->apply($this->serviceId, 'Annually', $current + 10.0, 1);

        $this->assertNotNull($applied);

        $service = $this->repository->findService($this->serviceId);

        $this->assertSame($current + 10.0, (float)$service['amount']);
        $this->assertSame('Annually', (string)$service['billingcycle']);

        // The change is in the client's notes — as the WHMCS does.
        $notes = $this->pdo->prepare(
            'SELECT note FROM tblnotes WHERE userid = (SELECT userid FROM tblhosting WHERE id = ?) '
            . 'ORDER BY id DESC LIMIT 1'
        );
        $notes->execute([$this->serviceId]);

        $note = (string)$notes->fetchColumn();

        $this->assertStringContainsString(sprintf('%.2f', $current + 10.0), $note);
        $this->assertStringContainsString('Prorrateio', $note);
    }

    public function testADifferenceInTheCustomersFavourBecomesCredit(): void
    {
        $client = (new ClientRepositoryPDO($this->pdo))->findByEmail('xkhm-test@example.com');
        $before = (float)($client['credit'] ?? 0);

        // The price is cut, with a year left: the customer is owed.
        $this->pdo->prepare(
            'UPDATE tblhosting SET amount = ?, nextduedate = ? WHERE id = ?'
        )->execute([100.0, date('Y-m-d', strtotime('+365 days')), $this->serviceId]);

        $applied = $this->service->apply($this->serviceId, 'Annually', 60.0, 1);

        $this->assertNotNull($applied);
        $this->assertGreaterThan(0.0, (float)$applied['credit']);

        $after = (new ClientRepositoryPDO($this->pdo))->findByEmail('xkhm-test@example.com');

        $this->assertEqualsWithDelta(
            $before + (float)$applied['credit'],
            (float)($after['credit'] ?? 0),
            0.02
        );
    }

    public function testAnUnknownCycleIsRefused(): void
    {
        $this->assertNull($this->service->quote($this->serviceId, 'A Cada Quinze Dias'));
        $this->assertNull($this->service->apply($this->serviceId, 'A Cada Quinze Dias'));
    }

    public function testAServiceThatDoesNotExistIsRefused(): void
    {
        $this->assertNull($this->service->quote(99999999, 'Annually'));
    }
}

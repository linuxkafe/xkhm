<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\AdminPanelRepositoryPDO;

/**
 * The queries of the administration panel, against the **real** database.
 *
 * The rules that matter here:
 *
 * - the counts and the lists agree;
 * - the filters work;
 * - the pagination works;
 * - and the panel never touches `tblactivitylog`, which has 2.6 million
 *   rows in the production database.
 */
class AdminPanelRepositoryTest extends TestCase
{
    private ?PDO $pdo = null;
    private AdminPanelRepositoryPDO $repository;

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->repository = new AdminPanelRepositoryPDO($this->pdo);
    }

    public function testTheDashboardCountsTheRealRows(): void
    {
        $data = $this->repository->dashboard();

        $this->assertGreaterThan(0, $data['clients']['total']);
        $this->assertGreaterThan(0, $data['invoices']['total']);
        $this->assertGreaterThan(0, $data['tickets']['total']);

        // The paid invoices are a subset of all of them.
        $this->assertLessThanOrEqual(
            $data['invoices']['total'],
            $data['invoices']['paid']
        );
    }

    public function testTheDashboardMatchesTheRepository(): void
    {
        $data = $this->repository->dashboard();

        $this->assertSame($this->repository->countClients(), $data['clients']['total']);
        $this->assertSame($this->repository->countInvoices(), $data['invoices']['total']);
        $this->assertSame($this->repository->countTickets(), $data['tickets']['total']);
    }

    public function testTheClientListAndTheCountAgree(): void
    {
        $total = $this->repository->countClients();
        $clients = $this->repository->listClients(null, 25, 0);

        $this->assertLessThanOrEqual($total, count($clients));
        $this->assertSame(
            min(25, $total),
            count($clients),
            'the first page holds a full page, or everything that is left'
        );
    }

    public function testTheSearchNarrowsTheClients(): void
    {
        $all = $this->repository->countClients();

        $client = $this->repository->listClients(null, 1, 0)[0] ?? null;
        $this->assertNotNull($client);

        $email = (string)$client['email'];
        $term = substr($email, 0, strpos($email, '@') ?: 5);

        $found = $this->repository->listClients($term, 25, 0);

        $this->assertNotEmpty($found, 'the search must find the client it started from');
        $this->assertLessThanOrEqual($all, $this->repository->countClients($term));
    }

    public function testTheSearchIsNotSqlInjection(): void
    {
        // A term that would break the query must simply find nothing.
        $found = $this->repository->listClients("'; DROP TABLE tblclients; --", 25, 0);

        $this->assertSame([], $found);
        // And the table is still there.
        $this->assertGreaterThan(0, $this->repository->countClients());
    }

    public function testTheInvoiceFilterWorks(): void
    {
        $paid = $this->repository->countInvoices('Paid');
        $unpaid = $this->repository->countInvoices('Unpaid');
        $all = $this->repository->countInvoices();

        $cancelled = $this->repository->countInvoices('Cancelled');

        $this->assertGreaterThan(0, $paid);
        // Every invoice is in exactly one of the three states.
        $this->assertSame($all, $paid + $unpaid + $cancelled);

        $rows = $this->repository->listInvoices('Paid', null, 25, 0);

        foreach ($rows as $row) {
            $this->assertSame('Paid', $row['status']);
        }
    }

    public function testTheTicketFilterWorks(): void
    {
        $rows = $this->repository->listTickets('Open', null, 25, 0);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('Open', $row['status']);
        }
    }

    public function testThePaginationDoesNotRepeatRows(): void
    {
        $total = $this->repository->countInvoices();
        $this->assertGreaterThan(25, $total, 'the test needs more rows than a page');

        $first = $this->repository->listInvoices(null, null, 25, 0);
        $second = $this->repository->listInvoices(null, null, 25, 25);

        $firstIds = array_column($first, 'id');
        $secondIds = array_column($second, 'id');

        $this->assertCount(25, $firstIds);
        $this->assertCount(25, $secondIds);
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    public function testTheClientDetailHasTheClientsOwnRows(): void
    {
        $client = $this->repository->listClients(null, 1, 0)[0] ?? null;
        $this->assertNotNull($client);

        $id = (int)$client['id'];

        foreach (
            [
            'clientInvoices',
            'clientOrders',
            'clientDomains',
            'clientTickets',
            'clientTransactions',
            ] as $method
        ) {
            foreach ($this->repository->{$method}($id) as $row) {
                $this->assertSame(
                    $id,
                    (int)$row['userid'],
                    $method . ' must only return the rows of that client'
                );
            }
        }
    }

    public function testANonExistentClientIsNull(): void
    {
        $this->assertNull($this->repository->findClient(99999999));
    }

    public function testTheInvoiceDetailHasItsItems(): void
    {
        $invoice = $this->repository->listInvoices(null, null, 1, 0)[0] ?? null;
        $this->assertNotNull($invoice);

        $items = $this->repository->invoiceItems((int)$invoice['id']);

        foreach ($items as $item) {
            $this->assertSame((int)$invoice['id'], (int)$item['invoiceid']);
        }
    }

    public function testTheGlobalSearchFindsAcrossEntities(): void
    {
        $client = $this->repository->listClients(null, 1, 0)[0] ?? null;
        $this->assertNotNull($client);

        $email = (string)$client['email'];
        $results = $this->repository->globalSearch($email);

        $this->assertNotEmpty($results['clients']);
        $this->assertSame(
            $email,
            (string)$results['clients'][0]['email'],
            'the client must come first'
        );
    }

    public function testTheGlobalSearchWithNothingFindsNothing(): void
    {
        $results = $this->repository->globalSearch('');

        foreach ($results as $rows) {
            $this->assertSame([], $rows);
        }
    }

    public function testTheConfigurationIsRead(): void
    {
        $settings = $this->repository->configuration();

        $this->assertNotEmpty($settings);

        $company = $this->repository->configurationValue('CompanyName');
        $this->assertNotNull($company);
    }

    /**
     * The source without its comments.
     *
     * A guard that greps the file has to ignore the comments: the
     * comment that *says* the table is never touched must not be
     * mistaken for a query that touches it.
     */
    private function withoutComments(string $source): string
    {
        $withoutBlock = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

        return preg_replace('#//[^\n]*#', '', $withoutBlock) ?? $withoutBlock;
    }

    /**
     * The panel must never scan `tblactivitylog`.
     *
     * In the production database that table has 2.6 million rows. A
     * `COUNT(*)` over it would stall the server — and the panel is
     * opened several times a day.
     *
     * This is a **static** guard on purpose: a query that mentions the
     * table is refused at review time, not discovered in production.
     */
    public function testThePanelNeverScansTheActivityLog(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Repository/AdminPanelRepositoryPDO.php'
        );

        $this->assertIsString($source);

        // The **code**, not the comment about the code: a comment that
        // names the table must not trip the guard.
        $code = $this->withoutComments($source);

        $this->assertStringNotContainsStringIgnoringCase(
            'tblactivitylog',
            $code,
            'the panel must never query tblactivitylog — it has 2.6 million rows'
        );
    }

    /**
     * And the queries must be parameterised.
     */
    public function testTheQueriesDoNotConcatenateTheSearch(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Repository/AdminPanelRepositoryPDO.php'
        );

        $this->assertIsString($source);
        $source = $this->withoutComments($source);

        // A value from the user must never be glued into the SQL.
        $this->assertStringNotContainsString(
            "'\$search'",
            $source,
            'the search term must be a bound parameter'
        );
        $this->assertStringNotContainsString(
            "'\$status'",
            $source,
            'the status must be a bound parameter'
        );
    }
}

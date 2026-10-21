<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\AffiliateRepositoryPDO;
use Xkhm\Repository\AnnouncementRepositoryPDO;
use Xkhm\Repository\ClientRepositoryPDO;
use Xkhm\Repository\DomainRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryPDO;
use Xkhm\Repository\KnowledgebaseRepositoryPDO;
use Xkhm\Repository\OrderRepositoryPDO;
use Xkhm\Repository\TicketRepositoryPDO;

/**
 * Verifies that the repositories work against the real `linuxkonect` (WHMCS)
 * schema.
 *
 * This is the automated guard for the operator's hard requirement: the
 * service must stay fully compatible with the existing WHMCS database.
 * Column-name drift (e.g. `tbldomains.name` instead of `tbldomains.domain`)
 * is silent in unit tests and only explodes at runtime.
 *
 * Unlike a hand-written list of SQL literals, this test **calls the
 * repositories themselves** — a copy of the SQL in the test file can drift
 * away from the code it is supposed to guard, and then it protects nothing.
 */
class SchemaParityTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredTableProvider(): array
    {
        return [
            'admins' => ['tbladmins'],
            'affiliates' => ['tblaffiliates'],
            'affiliate history' => ['tblaffiliateshistory'],
            'affiliate withdrawals' => ['tblaffiliateswithdrawals'],
            'affiliate pending' => ['tblaffiliatespending'],
            'affiliate accounts' => ['tblaffiliatesaccounts'],
            'announcements' => ['tblannouncements'],
            'clients' => ['tblclients'],
            'domain pricing' => ['tbldomainpricing'],
            'domains' => ['tbldomains'],
            'invoice items' => ['tblinvoiceitems'],
            'invoices' => ['tblinvoices'],
            'knowledgebase' => ['tblknowledgebase'],
            'knowledgebase categories' => ['tblknowledgebasecats'],
            'orders' => ['tblorders'],
            'ticket departments' => ['tblticketdepartments'],
            'ticket replies' => ['tblticketreplies'],
            'tickets' => ['tbltickets'],
        ];
    }

    /**
     * @dataProvider requiredTableProvider
     */
    public function testRequiredTableExists(string $table): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), "Table {$table} is missing");
    }

    /**
     * The tables the repositories touch must all be present.
     */
    public function testAllRequiredTablesExist(): void
    {
        foreach (self::requiredTableProvider() as $case) {
            $this->testRequiredTableExists($case[0]);
        }
    }

    // ─── Read paths ──────────────────────────────────────────────────────────

    public function testClientRepositoryReads(): void
    {
        $repository = new ClientRepositoryPDO($this->pdo);

        // The first client that exists in the real database.
        $id = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();
        $this->assertGreaterThan(0, $id, 'tblclients must not be empty');

        $client = $repository->findById($id);
        $this->assertIsArray($client, "tblclients row {$id} must be readable");

        $byEmail = $repository->findByEmail((string)$client['email']);
        $this->assertIsArray($byEmail);
        $this->assertSame($client['id'], $byEmail['id']);
    }

    public function testAdminRepositoryReads(): void
    {
        $repository = new \Xkhm\Repository\AdminRepositoryPDO($this->pdo);

        $username = (string) $this->pdo->query('SELECT MIN(username) FROM tbladmins')->fetchColumn();
        $this->assertNotSame('', $username, 'tbladmins must not be empty');

        $admin = $repository->findByUsername($username);
        $this->assertIsArray($admin, "tbladmins row '{$username}' must be readable");
        // The display name is synthesised from the two name columns.
        $this->assertArrayHasKey('fullname', $admin);
    }

    public function testDomainRepositoryReads(): void
    {
        $repository = new DomainRepositoryPDO($this->pdo);

        $domains = $repository->findByUserId(1);
        $this->assertIsArray($domains);

        $pricing = $repository->getPricing(['.com']);
        $this->assertArrayHasKey('.com', $pricing);

        if ($domains !== []) {
            $domain = $repository->findByName((string)$domains[0]['domain']);
            $this->assertIsArray($domain, 'findByName must find the domain');
        }
    }

    public function testInvoiceRepositoryReads(): void
    {
        $repository = new InvoiceRepositoryPDO($this->pdo);

        $invoices = $repository->findByUserId(1);
        $this->assertIsArray($invoices);

        if ($invoices !== []) {
            $invoice = $repository->findById((int)$invoices[0]['id']);
            $this->assertIsArray($invoice);
        }
    }

    public function testOrderRepositoryReads(): void
    {
        $repository = new OrderRepositoryPDO($this->pdo);

        $orders = $repository->findByUserId(1);
        $this->assertIsArray($orders);
    }

    public function testTicketRepositoryReads(): void
    {
        $repository = new TicketRepositoryPDO($this->pdo);

        $tickets = $repository->findByUserId(1);
        $this->assertIsArray($tickets);

        $departments = $repository->getDepartments();
        $this->assertIsArray($departments);

        if ($tickets !== []) {
            $ticket = $repository->findWithReplies((int)$tickets[0]['id']);
            $this->assertIsArray($ticket);
            $this->assertArrayHasKey('replies', $ticket);
        }
    }

    public function testKnowledgebaseRepositoryReads(): void
    {
        $repository = new KnowledgebaseRepositoryPDO($this->pdo);

        $this->assertIsArray($repository->getCategories());
        $this->assertIsArray($repository->getPublishedArticles());
        $this->assertIsArray($repository->searchArticles('a', 10));
    }

    public function testAnnouncementRepositoryReads(): void
    {
        $repository = new AnnouncementRepositoryPDO($this->pdo);

        $this->assertIsArray($repository->getPublished(5));
    }

    public function testAffiliateRepositoryReads(): void
    {
        $repository = new AffiliateRepositoryPDO($this->pdo);

        $affiliate = $repository->findByUserId(1);
        $this->assertNotFalse($affiliate, 'findByUserId must not fail');

        $byRef = $repository->findByRefCode('LINUXKAFE10');
        $this->assertNotFalse($byRef, 'findByRefCode must not fail');
    }

    // ─── Write paths ─────────────────────────────────────────────────────────
    //
    // The write paths are where the column drift actually bit: a wrong
    // column name only fails at INSERT time, and only against the real
    // schema.

    public function testTicketRepositoryWrites(): void
    {
        $repository = new TicketRepositoryPDO($this->pdo);
        $clientId = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();

        $ticketId = $repository->create([
            'userid' => $clientId,
            'name' => 'Schema Parity',
            'email' => 'parity@example.com',
            'subject' => 'Schema parity probe',
            'message' => 'Created by SchemaParityTest.',
            'priority' => 'Medium',
            'status' => 'Open',
            'deptid' => 1,
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertGreaterThan(0, (int)$ticketId);

        $replyId = $repository->addReply($ticketId, [
            'userid' => $clientId,
            'name' => 'Schema Parity',
            'email' => 'parity@example.com',
            'message' => 'Reply from SchemaParityTest.',
            'adminreply' => 0,
        ]);

        $this->assertGreaterThan(0, (int)$replyId);

        $this->pdo->prepare('DELETE FROM tblticketreplies WHERE tid = ?')->execute([$ticketId]);
        $this->pdo->prepare('DELETE FROM tbltickets WHERE id = ?')->execute([$ticketId]);
    }

    public function testDomainRepositoryWrites(): void
    {
        $repository = new DomainRepositoryPDO($this->pdo);
        $clientId = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();

        $domainId = $repository->register($clientId, 'schema-parity-probe.example', 1);
        $this->assertGreaterThan(0, (int)$domainId);

        $this->pdo->prepare('DELETE FROM tbldomains WHERE id = ?')->execute([$domainId]);
    }

    public function testDomainServiceRegisterUsesRealColumns(): void
    {
        $service = new \Xkhm\Service\DomainService(new DomainRepositoryPDO($this->pdo));

        $clientId = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();

        $result = $service->registerDomain(
            'schema-parity-service.example',
            $clientId,
            ['period' => 1]
        );

        $this->assertTrue($result[0], 'registerDomain must succeed: ' . $result[1]);
        $this->assertGreaterThan(0, (int)$result[2]);

        $this->pdo->prepare('DELETE FROM tbldomains WHERE id = ?')->execute([$result[2]]);
    }

    public function testTicketServiceCreateUsesRealColumns(): void
    {
        $service = new \Xkhm\Service\TicketService(new TicketRepositoryPDO($this->pdo));

        $clientId = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();

        $ticketId = $service->createTicket([
            'userid' => $clientId,
            'name' => 'Schema Parity',
            'email' => 'parity@example.com',
            'subject' => 'Service probe',
            'message' => 'Created by SchemaParityTest.',
            'priority' => 'Urgent',
        ]);

        $this->assertGreaterThan(0, (int)$ticketId);

        $this->pdo->prepare('DELETE FROM tbltickets WHERE id = ?')->execute([$ticketId]);
    }

    public function testTicketServiceNormalisesAnInvalidPriority(): void
    {
        $service = new \Xkhm\Service\TicketService(new TicketRepositoryPDO($this->pdo));

        // `tbltickets.priority` is an ENUM: an unknown value would abort the
        // INSERT under STRICT_TRANS_TABLES.
        $clientId = (int) $this->pdo->query('SELECT MIN(id) FROM tblclients')->fetchColumn();

        $ticketId = $service->createTicket([
            'userid' => $clientId,
            'name' => 'Schema Parity',
            'email' => 'parity@example.com',
            'subject' => 'Invalid priority probe',
            'message' => 'Created by SchemaParityTest.',
            'priority' => 'not-a-valid-priority',
        ]);

        $this->assertGreaterThan(0, (int)$ticketId);

        $this->pdo->prepare('DELETE FROM tbltickets WHERE id = ?')->execute([$ticketId]);
    }
}

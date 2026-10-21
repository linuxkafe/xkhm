<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Verifies that every SQL statement used by the repositories is valid against
 * the real `linuxkonect` (WHMCS) schema.
 *
 * This is the automated guard for the operator's hard requirement: the service
 * must stay fully compatible with the existing WHMCS database. Column-name
 * drift (e.g. `tbldomains.name` instead of `tbldomains.domain`) is silent in
 * unit tests and only explodes at runtime — exactly the class of defect the
 * Playwright suite hit.
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
     * Every SELECT used by the repositories, with the parameters it binds.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public static function selectProvider(): array
    {
        return [
            'admin by username' => [
                'SELECT id, username, password, fullname, email FROM tbladmins WHERE username = ?',
                ['admin'],
            ],
            'client by email' => [
                'SELECT id, firstname, lastname, email, password, status, credit FROM tblclients WHERE email = ?',
                ['test@example.com'],
            ],
            'client by id' => [
                'SELECT id, firstname, lastname, email, password, status, credit FROM tblclients WHERE id = ?',
                [1],
            ],
            'invoice by id' => [
                'SELECT id, userid, datecreated, duedate, status, total FROM tblinvoices WHERE id = ?',
                [1],
            ],
            'invoice items' => [
                'SELECT id, invoiceid, description, amount FROM tblinvoiceitems WHERE invoiceid = ?',
                [1],
            ],
            'invoices by user' => [
                'SELECT id, userid, datecreated, duedate, status, total FROM tblinvoices WHERE userid = ? ORDER BY datecreated DESC',
                [1],
            ],
            'order by id' => [
                'SELECT id, userid, date, status, total FROM tblorders WHERE id = ?',
                [1],
            ],
            'orders by user' => [
                'SELECT id, userid, date, status, total FROM tblorders WHERE userid = ? ORDER BY date DESC',
                [1],
            ],
            'domain by name' => [
                'SELECT * FROM tbldomains WHERE domain = ?',
                ['example.com'],
            ],
            'domains by user' => [
                'SELECT * FROM tbldomains WHERE userid = ? ORDER BY registrationdate DESC',
                [1],
            ],
            'domain pricing' => [
                'SELECT * FROM tbldomainpricing WHERE extension IN (?)',
                ['.com'],
            ],
            'ticket by id' => [
                'SELECT id, userid, name, email, subject, status, priority FROM tbltickets WHERE id = ?',
                [1],
            ],
            'tickets by user' => [
                'SELECT id, userid, name, email, subject, status, priority FROM tbltickets WHERE userid = ? ORDER BY date DESC',
                [1],
            ],
            'ticket replies' => [
                'SELECT id, tid, userid, name, message, date FROM tblticketreplies WHERE tid = ? ORDER BY date',
                [1],
            ],
            'ticket departments' => [
                'SELECT * FROM tblticketdepartments ORDER BY sortorder',
                [],
            ],
            'knowledgebase categories' => [
                'SELECT * FROM tblknowledgebasecats ORDER BY sortorder',
                [],
            ],
            'knowledgebase by category' => [
                'SELECT * FROM tblknowledgebase WHERE catid = ? AND published = 1 ORDER BY title LIMIT ?',
                [1, 10],
            ],
            'knowledgebase by id' => [
                'SELECT * FROM tblknowledgebase WHERE id = ? AND published = 1',
                [1],
            ],
            'knowledgebase search' => [
                'SELECT * FROM tblknowledgebase WHERE (title LIKE ? OR description LIKE ?) AND published = 1 ORDER BY lastupdated DESC LIMIT ?',
                ['%a%', '%a%', 10],
            ],
            'knowledgebase published' => [
                'SELECT * FROM tblknowledgebase WHERE published = 1 ORDER BY lastupdated DESC',
                [],
            ],
            'announcements published' => [
                'SELECT * FROM tblannouncements WHERE published = 1 ORDER BY date DESC LIMIT ?',
                [10],
            ],
            'announcement by id' => [
                'SELECT * FROM tblannouncements WHERE id = ? AND published = 1',
                [1],
            ],
            'affiliate by user' => [
                'SELECT * FROM tblaffiliates WHERE userid = ?',
                [1],
            ],
            'affiliate by id' => [
                'SELECT * FROM tblaffiliates WHERE id = ?',
                [1],
            ],
            'affiliate by refcode' => [
                'SELECT * FROM tblaffiliates WHERE refcode = ?',
                ['ABC123'],
            ],
            'affiliate clients' => [
                'SELECT * FROM tblaffiliatesclients WHERE affiliateid = ? ORDER BY date DESC',
                [1],
            ],
            'affiliate payouts' => [
                'SELECT * FROM tblaffiliatespayouts WHERE affiliateid = ? ORDER BY date DESC',
                [1],
            ],
        ];
    }

    /**
     * @dataProvider selectProvider
     */
    public function testSelectIsValidAgainstSchema(string $sql, array $params): void
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->assertTrue(true, 'Query executed without a schema error');
    }

    /**
     * The columns the repositories read must exist in the WHMCS schema.
     *
     * @dataProvider selectProvider
     */
    public function testSelectColumnsExist(string $sql, array $params): void
    {
        $table = $this->tableOf($sql);
        $columns = $this->extractColumns($sql);

        if ($columns === []) {
            // `SELECT *` — assert the table itself is resolvable.
            $exists = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $exists->execute([$table]);
            $this->assertGreaterThan(0, (int) $exists->fetchColumn(), "Table {$table} is missing");

            return;
        }

        foreach ($columns as $column) {
            $exists = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $exists->execute([$table, $column]);

            $this->assertGreaterThan(
                0,
                (int) $exists->fetchColumn(),
                sprintf('Column %s.%s does not exist in the WHMCS schema', $table, $column)
            );
        }
    }

    /**
     * The tables the repositories touch must all be present.
     */
    public function testAllRequiredTablesExist(): void
    {
        $required = [
            'tbladmins',
            'tblaffiliates',
            'tblaffiliatesclients',
            'tblaffiliatespayouts',
            'tblannouncements',
            'tblclients',
            'tbldomainpricing',
            'tbldomains',
            'tblinvoiceitems',
            'tblinvoices',
            'tblknowledgebase',
            'tblknowledgebasecats',
            'tblorders',
            'tblticketdepartments',
            'tblticketreplies',
            'tbltickets',
        ];

        foreach ($required as $table) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);

            $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), "Table {$table} is missing");
        }
    }

    private function tableOf(string $sql): string
    {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function extractColumns(string $sql): array
    {
        if (preg_match('/SELECT\s+(.+?)\s+FROM/i', $sql, $m) !== 1) {
            return [];
        }

        $selectList = $m[1];

        if (str_contains($selectList, '*')) {
            return [];
        }

        $columns = [];
        foreach (explode(',', $selectList) as $part) {
            $part = trim($part);
            // Strip table qualifiers and aliases.
            $part = preg_replace('/\s+AS\s+.+$/i', '', $part) ?? $part;
            $part = explode('.', $part)[0];
            $part = trim($part, " `\t");
            if ($part !== '' && !str_contains($part, '(')) {
                $columns[] = $part;
            }
        }

        return $columns;
    }
}

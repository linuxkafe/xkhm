<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * The read queries of the administration panel, against the real
 * WHMCS 5.3.9 schema.
 *
 * Two rules shape this class:
 *
 * - **nothing is concatenated.** Every value that comes from the user
 *   (the search, the status, the id) is a bound parameter. The only
 *   things inlined are the `LIMIT` and the `OFFSET`, which are
 *   integers the caller has already validated;
 * - **`tblactivitylog` is never touched.** It has 2.6 million rows in
 *   the production database; a `COUNT(*)` over it would stall the
 *   server. The "recent activity" of the dashboard comes from the
 *   indexed date columns of the entities themselves.
 */
class AdminPanelRepositoryPDO implements AdminPanelRepositoryInterface
{
    private const CLIENTS = 'tblclients';
    private const INVOICES = 'tblinvoices';
    private const INVOICE_ITEMS = 'tblinvoiceitems';
    private const ORDERS = 'tblorders';
    private const TICKETS = 'tbltickets';
    private const TICKET_REPLIES = 'tblticketreplies';
    private const DOMAINS = 'tbldomains';
    private const ACCOUNTS = 'tblaccounts';
    private const HOSTING = 'tblhosting';
    private const AFFILIATES = 'tblaffiliates';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    // ─── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard(): array
    {
        $today = date('Y-m-d');

        return [
            'clients' => [
                'total' => $this->countClients(),
                'active' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tblclients WHERE status = ?',
                    ['Active']
                ),
            ],
            'invoices' => [
                'total' => $this->countInvoices(),
                'paid' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tblinvoices WHERE status = ?',
                    ['Paid']
                ),
                'unpaid' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tblinvoices WHERE status = ?',
                    ['Unpaid']
                ),
                'overdue' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tblinvoices WHERE status = ? AND duedate < ?',
                    ['Unpaid', $today]
                ),
                'revenue' => (float)$this->scalar(
                    "SELECT COALESCE(SUM(total), 0) FROM tblinvoices WHERE status = 'Paid'"
                ),
                'outstanding' => (float)$this->scalar(
                    "SELECT COALESCE(SUM(total), 0) FROM tblinvoices WHERE status = 'Unpaid'"
                ),
            ],
            'orders' => [
                'total' => $this->countOrders(),
                'active' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tblorders WHERE status = ?',
                    ['Active']
                ),
            ],
            'tickets' => [
                'total' => $this->countTickets(),
                'open' => (int)$this->scalar(
                    'SELECT COUNT(*) FROM tbltickets WHERE status = ?',
                    ['Open']
                ),
            ],
            'domains' => [
                'total' => $this->countDomains(),
            ],
            'recent_clients' => $this->listClients(null, 5, 0),
            'recent_invoices' => $this->listInvoices(null, null, 5, 0),
            'recent_tickets' => $this->listTickets(null, null, 5, 0),
        ];
    }

    // ─── Clients ────────────────────────────────────────────────────────────

    public function countClients(?string $search = null): int
    {
        return $this->count(self::CLIENTS, $search, ['firstname', 'lastname', 'email', 'companyname']);
    }

    public function listClients(?string $search, int $limit, int $offset): array
    {
        [$where] = $this->whereClause($search, ['firstname', 'lastname', 'email', 'companyname'], null);

        $sql = 'SELECT * FROM tblclients' . $where
            . ' ORDER BY datecreated DESC, id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset);

        return $this->rows($sql, $search, self::CLIENTS, ['firstname', 'lastname', 'email', 'companyname']);
    }

    public function findClient(int $id): ?array
    {
        $row = $this->one('SELECT * FROM tblclients WHERE id = ?', [$id]);

        return $row ? SchemaMap::toCanonical(self::CLIENTS, $row) : null;
    }

    public function clientInvoices(int $clientId, int $limit = 25): array
    {
        $rows = $this->all(
            'SELECT * FROM tblinvoices WHERE userid = ? ORDER BY date DESC LIMIT ' . $this->int($limit),
            [$clientId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::INVOICES, $row),
            $rows
        );
    }

    public function clientOrders(int $clientId, int $limit = 25): array
    {
        $rows = $this->all(
            'SELECT * FROM tblorders WHERE userid = ? ORDER BY id DESC LIMIT ' . $this->int($limit),
            [$clientId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::ORDERS, $row),
            $rows
        );
    }

    public function clientDomains(int $clientId, int $limit = 25): array
    {
        $rows = $this->all(
            'SELECT * FROM tbldomains WHERE userid = ? ORDER BY id DESC LIMIT ' . $this->int($limit),
            [$clientId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::DOMAINS, $row),
            $rows
        );
    }

    public function clientTickets(int $clientId, int $limit = 25): array
    {
        $rows = $this->all(
            'SELECT * FROM tbltickets WHERE userid = ? ORDER BY id DESC LIMIT ' . $this->int($limit),
            [$clientId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TICKETS, $row),
            $rows
        );
    }

    public function clientTransactions(int $clientId, int $limit = 25): array
    {
        $rows = $this->all(
            'SELECT * FROM tblaccounts WHERE userid = ? ORDER BY date DESC, id DESC LIMIT ' . $this->int($limit),
            [$clientId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::ACCOUNTS, $row),
            $rows
        );
    }

    public function clientServices(int $clientId): array
    {
        return $this->services('WHERE h.userid = ?', [$clientId]);
    }

    public function findService(int $id): ?array
    {
        $services = $this->services('WHERE h.id = ?', [$id]);

        return $services[0] ?? null;
    }

    public function productPrices(int $packageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT monthly, quarterly, semiannually, annually, biennially, triennially '
            . 'FROM tblpricing WHERE type = ? AND relid = ? AND currency = ? LIMIT 1'
        );
        $stmt->execute(['product', $packageId, 1]);

        $row = $stmt->fetch();

        if ($row === false) {
            return [];
        }

        $prices = [];

        foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $column) {
            $prices[$column] = (float)($row[$column] ?? -1);
        }

        return $prices;
    }

    public function updateServiceTerms(
        int $id,
        float $amount,
        string $billingCycle,
        string $nextDueDate
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE tblhosting SET amount = :amount, billingcycle = :cycle, '
            . 'nextduedate = :next WHERE id = :id'
        );

        return $stmt->execute([
            'amount' => $amount,
            'cycle' => $billingCycle,
            'next' => $nextDueDate,
            'id' => $id,
        ]);
    }

    public function addNote(int $clientId, int $adminId, string $note): bool
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO tblnotes (userid, adminid, created, modified, note, sticky) '
            . 'VALUES (?, ?, ?, ?, ?, 0)'
        );

        return $stmt->execute([$clientId, $adminId, $now, $now, $note]);
    }

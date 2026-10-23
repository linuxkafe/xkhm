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

    /**
     * The services, with the product and the group they belong to.
     *
     * The **group** is the label the WHMCS shows the client
     * («Web Hosting», «Backup», «Servidores Virtuais»); the raw `type`
     * is `hostingaccount`, `other` or `server`, which is what the
     * machine understands. Both travel, and the interface decides.
     *
     * @param list<mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function services(string $where, array $parameters): array
    {
        $sql = 'SELECT h.*, '
            . 'p.name AS productname, p.type AS producttype, '
            . 'g.name AS groupname, g.id AS groupid '
            . 'FROM tblhosting h '
            . 'LEFT JOIN tblproducts p ON p.id = h.packageid '
            . 'LEFT JOIN tblproductgroups g ON g.id = p.gid '
            . $where
            . ' ORDER BY h.id DESC';

        $rows = $this->all($sql, $parameters);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::HOSTING, $row),
            $rows
        );
    }

    // ─── Invoices ───────────────────────────────────────────────────────────

    public function countInvoices(?string $status = null, ?string $search = null): int
    {
        return $this->count(self::INVOICES, $search, ['invoicenum'], $status);
    }

    public function listInvoices(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array {
        [$where] = $this->whereClause($search, ['invoicenum'], $status);

        $sql = 'SELECT * FROM tblinvoices' . $where
            . ' ORDER BY date DESC, id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset);

        return $this->rows($sql, $search, self::INVOICES, ['invoicenum'], $status);
    }

    public function findInvoice(int $id): ?array
    {
        $row = $this->one('SELECT * FROM tblinvoices WHERE id = ?', [$id]);

        return $row ? SchemaMap::toCanonical(self::INVOICES, $row) : null;
    }

    public function invoiceItems(int $invoiceId): array
    {
        $rows = $this->all(
            'SELECT * FROM tblinvoiceitems WHERE invoiceid = ? ORDER BY id',
            [$invoiceId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::INVOICE_ITEMS, $row),
            $rows
        );
    }

    // ─── Orders ─────────────────────────────────────────────────────────────

    public function countOrders(?string $status = null, ?string $search = null): int
    {
        return $this->count(self::ORDERS, $search, ['ordernum'], $status);
    }

    public function listOrders(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array {
        [$where] = $this->whereClause($search, ['ordernum'], $status);

        $sql = 'SELECT * FROM tblorders' . $where
            . ' ORDER BY id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset);

        return $this->rows($sql, $search, self::ORDERS, ['ordernum'], $status);
    }

    public function findOrder(int $id): ?array
    {
        $row = $this->one('SELECT * FROM tblorders WHERE id = ?', [$id]);

        return $row ? SchemaMap::toCanonical(self::ORDERS, $row) : null;
    }

    // ─── Tickets ────────────────────────────────────────────────────────────

    public function countTickets(?string $status = null, ?string $search = null): int
    {
        return $this->count(self::TICKETS, $search, ['title', 'tid', 'name', 'email'], $status);
    }

    public function listTickets(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array {
        [$where] = $this->whereClause($search, ['title', 'tid', 'name', 'email'], $status);

        $sql = 'SELECT * FROM tbltickets' . $where
            . ' ORDER BY id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset);

        return $this->rows($sql, $search, self::TICKETS, ['title', 'tid', 'name', 'email'], $status);
    }

    public function findTicket(int $id): ?array
    {
        $row = $this->one('SELECT * FROM tbltickets WHERE id = ?', [$id]);

        return $row ? SchemaMap::toCanonical(self::TICKETS, $row) : null;
    }

    public function ticketReplies(int $ticketId): array
    {
        $rows = $this->all(
            'SELECT * FROM tblticketreplies WHERE tid = ? ORDER BY date, id',
            [$ticketId]
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TICKET_REPLIES, $row),
            $rows
        );
    }

    // ─── Domains ────────────────────────────────────────────────────────────

    public function countDomains(?string $search = null): int
    {
        return $this->count(self::DOMAINS, $search, ['domain', 'registrar']);
    }

    public function listDomains(?string $search, int $limit, int $offset): array
    {
        [$where] = $this->whereClause($search, ['domain', 'registrar'], null);

        $sql = 'SELECT * FROM tbldomains' . $where
            . ' ORDER BY id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset);

        return $this->rows($sql, $search, self::DOMAINS, ['domain', 'registrar']);
    }

    public function findDomain(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return SchemaMap::toCanonical(self::DOMAINS, $row);
    }

    public function updateDomain(int $id, array $data): bool
    {
        $real = SchemaMap::toReal(self::DOMAINS, $data);

        if ($real === []) {
            return false;
        }

        $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($real)));
        $real['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE tbldomains SET $set WHERE id = :id");

        return $stmt->execute($real);
    }

    // ─── Affiliates ─────────────────────────────────────────────────────────

    public function countAffiliates(): int
    {
        return (int)$this->scalar('SELECT COUNT(*) FROM tblaffiliates');
    }

    public function listAffiliates(int $limit, int $offset): array
    {
        // The affiliate's name and e-mail live in the client row: in
        // WHMCS 5.3.9 the affiliate table has only the link.
        $rows = $this->all(
            'SELECT a.*, c.firstname, c.lastname, c.email AS clientemail '
            . 'FROM tblaffiliates a LEFT JOIN tblclients c ON c.id = a.clientid '
            . 'ORDER BY a.id DESC LIMIT ' . $this->int($limit)
            . ' OFFSET ' . $this->int($offset)
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::AFFILIATES, $row),
            $rows
        );
    }

    // ─── The global search ──────────────────────────────────────────────────

    public function globalSearch(string $term, int $limit = 10): array
    {
        $term = trim($term);

        if ($term === '') {
            return ['clients' => [], 'invoices' => [], 'tickets' => [], 'domains' => [], 'orders' => []];
        }

        $like = '%' . $term . '%';
        $limit = $this->int($limit);

        $clients = $this->all(
            'SELECT * FROM tblclients WHERE firstname LIKE ? OR lastname LIKE ? '
            . 'OR email LIKE ? OR companyname LIKE ? ORDER BY id DESC LIMIT ' . $limit,
            [$like, $like, $like, $like]
        );

        $invoices = $this->all(
            'SELECT * FROM tblinvoices WHERE invoicenum LIKE ? ORDER BY id DESC LIMIT ' . $limit,
            [$like]
        );

        $tickets = $this->all(
            'SELECT * FROM tbltickets WHERE title LIKE ? OR tid LIKE ? OR email LIKE ? '
            . 'ORDER BY id DESC LIMIT ' . $limit,
            [$like, $like, $like]
        );

        $domains = $this->all(
            'SELECT * FROM tbldomains WHERE domain LIKE ? ORDER BY id DESC LIMIT ' . $limit,
            [$like]
        );

        $orders = $this->all(
            'SELECT * FROM tblorders WHERE ordernum LIKE ? ORDER BY id DESC LIMIT ' . $limit,
            [$like]
        );

        return [
            'clients' => array_map(
                fn (array $row): array => SchemaMap::toCanonical(self::CLIENTS, $row),
                $clients
            ),
            'invoices' => array_map(
                fn (array $row): array => SchemaMap::toCanonical(self::INVOICES, $row),
                $invoices
            ),
            'tickets' => array_map(
                fn (array $row): array => SchemaMap::toCanonical(self::TICKETS, $row),
                $tickets
            ),
            'domains' => array_map(
                fn (array $row): array => SchemaMap::toCanonical(self::DOMAINS, $row),
                $domains
            ),
            'orders' => array_map(
                fn (array $row): array => SchemaMap::toCanonical(self::ORDERS, $row),
                $orders
            ),
        ];
    }

    // ─── The configuration ──────────────────────────────────────────────────

    public function configuration(string $prefix = ''): array
    {
        $sql = 'SELECT setting, value FROM tblconfiguration';

        $parameters = [];
        if ($prefix !== '') {
            $sql .= ' WHERE setting LIKE ?';
            $parameters[] = $prefix . '%';
        }

        $sql .= ' ORDER BY setting';

        $rows = $this->all($sql, $parameters);

        return array_map(
            fn (array $row): array => [
                'setting' => (string)$row['setting'],
                'value' => (string)$row['value'],
            ],
            $rows
        );
    }

    public function configurationValue(string $setting): ?string
    {
        $row = $this->one('SELECT value FROM tblconfiguration WHERE setting = ?', [$setting]);

        return $row === null ? null : (string)$row['value'];
    }

    // ─── The plumbing ───────────────────────────────────────────────────────

    /**
     * The `WHERE` of a list: the search **and** the status filter.
     *
     * The two are composed into one clause, because a filter without a
     * search has to produce a `WHERE` of its own — an `AND` without a
     * `WHERE` is a syntax error.
     *
     * The term is never concatenated: it is a bound parameter, and the
     * columns are a constant list from this class.
     *
     * @param list<string> $columns
     *
     * @return array{0: string, 1: list<string>} the clause and its parameters
     */
    private function whereClause(?string $search, array $columns, ?string $status): array
    {
        $conditions = [];
        $parameters = [];

        $search = $search === null ? '' : trim($search);

        if ($search !== '') {
            $parts = [];
            foreach ($columns as $column) {
                $parts[] = sprintf('%s LIKE ?', $column);
                $parameters[] = '%' . $search . '%';
            }

            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }

        $status = $status === null ? '' : trim($status);

        if ($status !== '') {
            $conditions[] = 'status = ?';
            $parameters[] = $status;
        }

        $clause = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$clause, $parameters];
    }

    /**
     * The number of rows that match.
     */
    private function count(string $table, ?string $search, array $columns, ?string $status = null): int
    {
        [$where, $parameters] = $this->whereClause($search, $columns, $status);

        return (int)$this->scalar('SELECT COUNT(*) FROM ' . $table . $where, $parameters);
    }

    /**
     * The rows, in the canonical shape.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(
        string $sql,
        ?string $search,
        string $table,
        array $columns,
        ?string $status = null
    ): array {
        [, $parameters] = $this->whereClause($search, $columns, $status);

        $rows = $this->all($sql, $parameters);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical($table, $row),
            $rows
        );
    }

    /**
     * @param list<mixed> $parameters
     */
    private function all(string $sql, array $parameters = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $parameters = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param list<mixed> $parameters
     */
    private function scalar(string $sql, array $parameters = []): int|string|null
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * An integer, for the `LIMIT` and the `OFFSET`.
     */
    private function int(int $value): int
    {
        return max(0, $value);
    }
}

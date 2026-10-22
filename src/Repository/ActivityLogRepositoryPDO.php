<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * The activity log — `tblactivitylog`.
 *
 * The WHMCS keeps the same table for the same purpose: **who did
 * what, and when**. The billing cycle writes to it too, with the
 * user `system`.
 */
class ActivityLogRepositoryPDO
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    /**
     * Write an entry.
     */
    public function log(string $description, string $user = 'system', int $userId = 0, string $ip = ''): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tblactivitylog (date, description, user, userid, ipaddr) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            date('Y-m-d H:i:s'),
            $description,
            $user,
            $userId,
            $ip,
        ]);
    }

    /**
     * Whether an entry was already written.
     *
     * The guard that makes the notices **idempotent**: the log says
     * which notice was already sent, and for which invoice.
     */
    public function hasEntry(string $description): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tblactivitylog WHERE description = ?'
        );
        $stmt->execute([$description]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * The last entries.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblactivitylog ORDER BY id DESC LIMIT ' . max(1, $limit));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes the affiliate tables using the real WHMCS 5.3.9 schema.
 *
 * ## The model changed between WHMCS versions
 *
 * WHMCS 5.3.9 models the affiliate as **the client themselves**:
 *
 * ```
 * tblaffiliates          id, date, clientid, visitors, paytype, payamount,
 *                        onetime, balance, withdrawn
 * tblaffiliateshistory   id, affiliateid, date, affaccid, description, amount
 * tblaffiliateswithdrawals  id, affiliateid, date, amount
 * tblaffiliatespending   id, affaccid, amount, clearingdate
 * tblaffiliatesaccounts  id, affiliateid, relid, lastpaid
 * ```
 *
 * The WHMCS 6+ "programme" model — a separate affiliate record with
 * `name`, `email`, `refcode` and `commission`, plus `tblaffiliatesclients`
 * and `tblaffiliatespayouts` — **does not exist here**. The canonical
 * `refcode`/`commission` are therefore synthesised (see {@see SchemaMap}),
 * and the referrals live in `tblaffiliateshistory`.
 *
 * In the production database every one of these tables is **empty**: the
 * feature was never used. The application must therefore degrade to "no
 * affiliate programme" rather than fail.
 */
class AffiliateRepositoryPDO implements AffiliateRepositoryInterface
{
    private const TABLE = 'tblaffiliates';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function create(array $data): int
    {
        // WHMCS 5.3.9: the affiliate *is* the client.
        $data = [
            'clientid' => $data['userid'] ?? $data['clientid'] ?? 0,
            'date' => date('Y-m-d'),
            'visitors' => 0,
            'paytype' => $data['paytype'] ?? '',
            'payamount' => $data['commission'] ?? 0,
            'onetime' => 0,
            'balance' => 0,
            'withdrawn' => 0,
        ];

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblaffiliates ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE clientid = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByRefCode(string $code): ?array
    {
        // WHMCS 5.3.9 has no referral code: the affiliate is identified by
        // the client. There is nothing to look up.
        return null;
    }

    public function addReferral(array $data): int
    {
        $row = [
            'affiliateid' => $data['affiliateid'] ?? 0,
            'date' => $data['date'] ?? date('Y-m-d'),
            'affaccid' => $data['clientid'] ?? 0,
            'description' => $data['description'] ?? '',
            'amount' => $data['commission'] ?? 0,
        ];

        $row = SchemaMap::withRequiredDefaults('tblaffiliateshistory', $row);

        $columns = implode(', ', array_keys($row));
        $placeholders = ':' . implode(', :', array_keys($row));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblaffiliateshistory ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($row);

        return (int) $this->pdo->lastInsertId();
    }

    public function getReferralsByAffiliate(int $affiliateId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblaffiliateshistory WHERE affiliateid = ? ORDER BY date DESC'
        );
        $stmt->execute([$affiliateId]);

        return $stmt->fetchAll();
    }

    public function getEarningsByAffiliate(int $affiliateId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) as total FROM tblaffiliateshistory WHERE affiliateid = ?'
        );
        $stmt->execute([$affiliateId]);
        $result = $stmt->fetch();

        return (float)($result['total'] ?? 0);
    }

    public function getPayoutHistory(int $affiliateId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblaffiliateswithdrawals WHERE affiliateid = ? ORDER BY date DESC'
        );
        $stmt->execute([$affiliateId]);

        return $stmt->fetchAll();
    }
}

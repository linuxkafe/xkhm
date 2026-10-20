<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class AffiliateRepositoryPDO implements AffiliateRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function create(array $data): int
    {
        $data['refcode'] = $this->generateRefCode();
        $data['balance'] = 0;
        $data['unpaidbalance'] = 0;

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblaffiliates ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE userid = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByRefCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE refcode = ?');
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public function addReferral(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblaffiliatesclients ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function getReferralsByAffiliate(int $affiliateId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliatesclients WHERE affiliateid = ? ORDER BY date DESC');
        $stmt->execute([$affiliateId]);
        return $stmt->fetchAll();
    }

    public function getEarningsByAffiliate(int $affiliateId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(commission), 0) as total FROM tblaffiliatesclients WHERE affiliateid = ?'
        );
        $stmt->execute([$affiliateId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    public function getPayoutHistory(int $affiliateId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblaffiliatespayouts WHERE affiliateid = ? ORDER BY date DESC'
        );
        $stmt->execute([$affiliateId]);
        return $stmt->fetchAll();
    }

    private function generateRefCode(): string
    {
        return strtoupper(bin2hex(random_bytes(8))); // 16-char hex
    }
}

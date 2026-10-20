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

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliates WHERE userid = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId): int
    {
        $data = [
            'userid' => $userId,
            'percentage' => 0,
            'balance' => 0,
            'pending' => 0,
        ];

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblaffiliates ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function getCommissions(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliateshistory WHERE userid = ? ORDER BY date DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getWithdrawals(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblaffiliatespending WHERE userid = ? ORDER BY date DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

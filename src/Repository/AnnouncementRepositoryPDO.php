<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class AnnouncementRepositoryPDO implements AnnouncementRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function getPublished(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblannouncements WHERE published = 1 ORDER BY date DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblannouncements WHERE id = ? AND published = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}

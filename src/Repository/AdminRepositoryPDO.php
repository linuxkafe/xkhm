<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class AdminRepositoryPDO implements AdminRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbladmins WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }
}

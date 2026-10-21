<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads `tbladmins` using the real WHMCS 5.3.9 schema.
 *
 * The admin's display name is `firstname` + `lastname` in the real schema;
 * the canonical `fullname` is synthesised by {@see SchemaMap}.
 */
class AdminRepositoryPDO implements AdminRepositoryInterface
{
    private const TABLE = 'tbladmins';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbladmins WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }
}

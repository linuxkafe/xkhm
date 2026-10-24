<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tblorders` using the real WHMCS 5.3.9 schema.
 *
 * The order total is stored in `amount`; `tax`, `gateway` and `currency` do
 * not exist in this WHMCS version and are dropped on write.
 */
class OrderRepositoryPDO implements OrderRepositoryInterface
{
    private const TABLE = 'tblorders';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblorders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblorders WHERE userid = ? ORDER BY date DESC'
        );
        $stmt->execute([$userId]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function create(array $data): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblorders ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = SchemaMap::toReal(self::TABLE, $data);
        if ($data === []) {
            return false;
        }

        $set = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE tblorders SET $set WHERE id = :id");

        return $stmt->execute($data);
    }
}

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
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblannouncements WHERE published IN ('1', 'on', 'yes') ORDER BY date DESC LIMIT ?"
        );
        $stmt->execute([$limit]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical('tblannouncements', $row),
            $stmt->fetchAll()
        );
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblannouncements WHERE id = ? AND published IN ('1', 'on', 'yes')"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical('tblannouncements', $row) : null;
    }
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tblinvoices` using the real WHMCS 5.3.9 schema.
 *
 * The invoice date is stored in `date` (not `datecreated`) and the note in
 * `notes`; `currency` does not exist in this version.
 */
class InvoiceRepositoryPDO implements InvoiceRepositoryInterface
{
    private const TABLE = 'tblinvoices';
    private const ITEMS = 'tblinvoiceitems';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblinvoices WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblinvoices WHERE userid = ? ORDER BY date DESC'
        );
        $stmt->execute([$userId]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function findByIdWithItems(int $id): ?array
    {
        $invoice = $this->findById($id);
        if (!$invoice) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tblinvoiceitems WHERE invoiceid = ?');
        $stmt->execute([$id]);
        $invoice['items'] = array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::ITEMS, $row),
            $stmt->fetchAll()
        );

        return $invoice;
    }

    public function create(array $data): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblinvoices ($columns) VALUES ($placeholders)"
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

        $stmt = $this->pdo->prepare("UPDATE tblinvoices SET $set WHERE id = :id");

        return $stmt->execute($data);
    }

    public function getInvoiceTotal(int $id): ?float
    {
        $invoice = $this->findById($id);

        return $invoice === null ? null : (float)($invoice['total'] ?? 0);
    }
}

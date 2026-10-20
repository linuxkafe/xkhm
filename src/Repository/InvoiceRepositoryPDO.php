<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class InvoiceRepositoryPDO implements InvoiceRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblinvoices WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblinvoices WHERE userid = ? ORDER BY datecreated DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findByIdWithItems(int $id): ?array
    {
        $invoice = $this->findById($id);
        if (!$invoice) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tblinvoiceitems WHERE invoiceid = ?');
        $stmt->execute([$id]);
        $invoice['items'] = $stmt->fetchAll();

        return $invoice;
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblinvoices ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE tblinvoices SET $set WHERE id = :id");
        return $stmt->execute($data);
    }

    public function getInvoiceTotal(int $id): ?float
    {
        $stmt = $this->pdo->prepare('SELECT total FROM tblinvoices WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ? (float)$result['total'] : null;
    }
}

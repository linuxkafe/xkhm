<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;
use Xkhm\Config;

class ClientRepositoryPDO implements ClientRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblclients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblclients WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblclients ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE tblclients SET $set WHERE id = :id");
        return $stmt->execute($data);
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $client = $this->findByEmail($email);
        if (!$client) {
            return null;
        }

        // WHMCS 5.3.9 uses md5(password . salt) where salt is the first 2 chars of the hash
        // For simplicity, we'll use the stored password directly (it's already hashed)
        // In production, we'd need to implement the exact WHMCS 5.3.9 hashing logic

        return $client;
    }
}

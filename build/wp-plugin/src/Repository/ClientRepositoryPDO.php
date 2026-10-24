<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tblclients` using the real WHMCS 5.3.9 schema.
 *
 * The column names are translated by {@see SchemaMap}, so the rest of the
 * application speaks canonical names.
 */
class ClientRepositoryPDO implements ClientRepositoryInterface
{
    private const TABLE = 'tblclients';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblclients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblclients WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function create(array $data): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblclients ($columns) VALUES ($placeholders)"
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

        $stmt = $this->pdo->prepare("UPDATE tblclients SET $set WHERE id = :id");

        return $stmt->execute($data);
    }

    /**
     * Store a password-reset token.
     *
     * The WHMCS 5.3.9 schema already has `pwresetkey` and
     * `pwresetexpiry` for exactly this — no schema change is needed.
     */
    public function setResetToken(int $id, string $token, int $expires): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tblclients SET pwresetkey = :token, pwresetexpiry = :expires WHERE id = :id'
        );

        return $stmt->execute([
            'token' => $token,
            'expires' => $expires,
            'id' => $id,
        ]);
    }

    /**
     * Find the client a reset token belongs to.
     *
     * @return array<string, mixed>|null
     */
    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblclients WHERE pwresetkey = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    /**
     * Clear the reset token — it is single-use.
     */
    public function services(int $clientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.*, '
            . 'p.name AS productname, p.type AS producttype, '
            . 'g.name AS groupname '
            . 'FROM tblhosting h '
            . 'LEFT JOIN tblproducts p ON p.id = h.packageid '
            . 'LEFT JOIN tblproductgroups g ON g.id = p.gid '
            . 'WHERE h.userid = ? ORDER BY h.id DESC'
        );
        $stmt->execute([$clientId]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical('tblhosting', $row),
            $stmt->fetchAll()
        );
    }

    public function clearResetToken(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tblclients SET pwresetkey = "", pwresetexpiry = 0 WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }
}

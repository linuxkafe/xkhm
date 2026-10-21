<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tbladmins` using the real WHMCS 5.3.9 schema.
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

    /**
     * Set the administrator's password.
     *
     * **Both** hash columns are written: the WHMCS writes one of them on
     * the migration and the other on a later password change, and the
     * application tries both when verifying.
     */
    public function updatePassword(int $id, string $hash): bool
    {
        // The placeholder is used twice: with `ATTR_EMULATE_PREPARES =>
        // false` PDO maps named parameters to positional ones and cannot
        // reuse them.
        $stmt = $this->pdo->prepare(
            'UPDATE tbladmins SET password = :hash, passwordhash = :hash2, '
            . 'disabled = 0, loginattempts = 0 WHERE id = :id'
        );

        return $stmt->execute([
            'hash' => $hash,
            'hash2' => $hash,
            'id' => $id,
        ]);
    }
}

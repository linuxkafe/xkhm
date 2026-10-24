<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * The settings of the installation, in `tblconfiguration`.
 *
 * The table has a primary key on `setting` (and a `value`), and the
 * WHMCS writes a row per setting. A setting that is not there is a
 * setting that is empty — and `set()` creates it.
 */
class SettingsRepositoryPDO implements SettingsRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT setting, value FROM tblconfiguration');
        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['setting']] = (string)$row['value'];
        }

        return $out;
    }

    public function get(string $setting): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT value FROM tblconfiguration WHERE setting = ? LIMIT 1'
        );
        $stmt->execute([$setting]);

        $value = $stmt->fetchColumn();

        return $value === false ? null : (string)$value;
    }

    public function set(string $setting, string $value): bool
    {
        // The table has **no unique key**: `setting` is a `text` with a
        // non-unique index. An `ON DUPLICATE KEY UPDATE` therefore never
        // fires, and the imported database already carries duplicated
        // settings.
        //
        // So the write is: delete every row with that setting, and
        // write one. The result is exactly one row, with the new
        // value — and the duplicates disappear.
        $delete = $this->pdo->prepare(
            'DELETE FROM tblconfiguration WHERE setting = ?'
        );
        $delete->execute([$setting]);

        $insert = $this->pdo->prepare(
            'INSERT INTO tblconfiguration (setting, value) VALUES (?, ?)'
        );

        return $insert->execute([$setting, $value]);
    }

    public function setMany(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($values as $setting => $value) {
                if (!$this->set((string)$setting, (string)$value)) {
                    $this->pdo->rollBack();

                    return false;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return true;
    }
}

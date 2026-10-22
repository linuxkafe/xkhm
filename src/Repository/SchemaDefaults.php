<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;

/**
 * The columns a table requires, and that the caller does not set.
 *
 * The WHMCS declares almost every column as `NOT NULL` **without a
 * default**, and MySQL in strict mode refuses an insert that leaves
 * them out:
 *
 * ```
 * ERROR 1364 (HY000): Field 'description' doesn't have a default value
 * ```
 *
 * `tblproducts` has 70 such columns, and `tblservers` has 24. A list
 * written by hand for each of them rots at the first `ALTER TABLE` of
 * the WHMCS — so the list is read from the **schema**, and the
 * database is the source of truth.
 *
 * The columns whose neutral value would be a **lie** — the
 * enumerations and the dates — are left out on purpose: a missing one
 * has to fail loudly, rather than be filled with a value that means
 * nothing.
 *
 * This is the *neutral* layer, and it is not the same as the
 * `SchemaMap::REQUIRED_DEFAULTS` layer: those carry **semantic**
 * values (a domain's `type` is `Register`, an order's `date` is
 * now), and são escritos à mão porque o valor importa. Here the value
 * does not matter — only that the insert is accepted.
 */
final class SchemaDefaults
{
    /**
     * The types whose neutral value is a number.
     */
    private const NUMERIC = [
        'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
        'decimal', 'numeric', 'float', 'double', 'real',
    ];

    /**
     * The types that are left out: o seu valor neutro seria uma mentira.
     */
    private const NEVER_NEUTRAL = [
        'enum', 'set', 'date', 'datetime', 'timestamp', 'time', 'year',
    ];

    /**
     * The values the table needs, and that `$set` does not provide.
     *
     * @param list<string> $set
     *
     * @return array<string, string|int>
     */
    public static function neutral(PDO $pdo, string $table, array $set = []): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . "AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL "
            . "AND EXTRA <> 'auto_increment'"
        );
        $stmt->execute([$table]);

        $values = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $name = (string)$column['COLUMN_NAME'];
            $type = (string)$column['DATA_TYPE'];

            if (in_array($name, $set, true) || in_array($type, self::NEVER_NEUTRAL, true)) {
                continue;
            }

            $values[$name] = self::value($type);
        }

        return $values;
    }

    /**
     * The neutral value of a type: zero for the numbers, empty for the
     * text.
     */
    public static function value(string $type): string|int
    {
        return in_array($type, self::NUMERIC, true) ? 0 : '';
    }
}

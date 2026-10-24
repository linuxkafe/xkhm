<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\SettingsRepositoryInterface;

/**
 * The number of an invoice.
 *
 * The WHMCS numbers the invoices either by **sequence** (a counter, and
 * a format) or by the year and the year before. Here the counter is
 * `SequentialInvoiceNumberValue` and the format is
 * `SequentialInvoiceNumberFormat` — `{NUMBER}` in the imported
 * installation.
 *
 * Two invoices must never take the same number, so the counter is read
 * and written **inside a transaction**, with the row locked: two
 * requests at the same instant cannot take the same number.
 */
class InvoiceNumberService
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ?\PDO $pdo = null
    ) {
    }

    /**
     * The next number.
     */
    public function next(): string
    {
        $pdo = $this->pdo ?? $this->connection();

        $pdo->beginTransaction();

        try {
            $counter = $this->readCounter($pdo);

            $number = $this->format($counter);

            $this->writeCounter($pdo, $counter + 1);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return $number;
    }

    /**
     * The number, formatted.
     */
    public function format(int $counter): string
    {
        $format = trim((string)($this->settings->all()['SequentialInvoiceNumberFormat'] ?? ''));

        if ($format === '') {
            $format = '{NUMBER}';
        }

        return str_replace(
            ['{NUMBER}', '{YY}', '{YYYY}'],
            [
                (string)$counter,
                date('y'),
                date('Y'),
            ],
            $format
        );
    }

    /**
     * The counter, with the row locked.
     */
    private function readCounter(\PDO $pdo): int
    {
        $stmt = $pdo->prepare(
            'SELECT value FROM tblconfiguration WHERE setting = ? FOR UPDATE'
        );
        $stmt->execute(['SequentialInvoiceNumberValue']);

        $value = $stmt->fetchColumn();

        return (int)$value;
    }

    private function writeCounter(\PDO $pdo, int $counter): void
    {
        // The table has no unique key: the counter is written by
        // deleting and inserting, so there is exactly one.
        $delete = $pdo->prepare(
            'DELETE FROM tblconfiguration WHERE setting = ?'
        );
        $delete->execute(['SequentialInvoiceNumberValue']);

        $insert = $pdo->prepare(
            'INSERT INTO tblconfiguration (setting, value) VALUES (?, ?)'
        );
        $insert->execute(['SequentialInvoiceNumberValue', (string)$counter]);
    }

    private function connection(): \PDO
    {
        $config = new \Xkhm\Config();

        return \Xkhm\Database\Connection::get($config->getDbConfig());
    }
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * The services, against the real WHMCS 5.3.9 schema.
 *
 * The column names are the WHMCS's own: `tblhosting` has no
 * canonical rename, because the real names **are** the names the
 * rest of the application uses.
 */
class ServiceRepositoryPDO implements ServiceRepositoryInterface
{
    private const TABLE = 'tblhosting';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblhosting WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblhosting WHERE userid = ? ORDER BY id');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dueForInvoice(string $date, int $daysBefore): array
    {
        // A fatura de renovação é criada `CreateInvoiceDaysBefore`
        // dias antes da renovação — como no WHMCS.
        $limit = date('Y-m-d', strtotime($date . ' +' . max(0, $daysBefore) . ' days'));

        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblhosting "
            . "WHERE nextinvoicedate IS NOT NULL AND nextinvoicedate <= :limit "
            . "AND domainstatus NOT IN ('Terminated', 'Cancelled', 'Suspended') "
            . 'ORDER BY id'
        );
        $stmt->execute(['limit' => $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function overdue(string $date, int $days): array
    {
        $limit = date('Y-m-d', strtotime($date . ' -' . max(0, $days) . ' days'));

        // Os parametros sao **todos nomeados**: o PDO nao aceita
        // misturar os posicionais com os nomeados.
        $stmt = $this->pdo->prepare(
            "SELECT h.* FROM tblhosting h "
            . 'INNER JOIN tblinvoiceitems i ON i.relid = h.id AND i.type = :type '
            . 'INNER JOIN tblinvoices v ON v.id = i.invoiceid '
            . "WHERE v.status = 'Unpaid' AND v.duedate IS NOT NULL AND v.duedate < :limit "
            . "AND h.domainstatus NOT IN ('Terminated', 'Cancelled') "
            . 'GROUP BY h.id ORDER BY h.id'
        );
        $stmt->execute(['type' => 'Hosting', 'limit' => $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, self::TABLE, array_keys($data)),
            $data
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblhosting ($names) VALUES ($placeholders)");
        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($data)));
        $data['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE tblhosting SET $set WHERE id = :id");

        return $stmt->execute($data);
    }

    public function invoicedForPeriod(int $serviceId, string $dueDate): int
    {
        // A factura do serviço: um item de tipo `Hosting` que aponte
        // para ele, com o vencimento do periodo.
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tblinvoiceitems "
            . "WHERE type = 'Hosting' AND relid = ? AND duedate = ?"
        );
        $stmt->execute([$serviceId, $dueDate]);

        return (int)$stmt->fetchColumn();
    }
}

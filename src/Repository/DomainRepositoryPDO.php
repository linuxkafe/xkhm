<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tbldomains` using the real WHMCS 5.3.9 schema.
 *
 * The registration period is stored in `registrationperiod`; the columns
 * `dateadded`, `dns1`, `dns2` and `currency` do not exist in this version.
 */
class DomainRepositoryPDO implements DomainRepositoryInterface
{
    private const TABLE = 'tbldomains';
    private const PRICING = 'tbldomainpricing';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function checkAvailability(string $domain, array $tlds): array
    {
        $results = [];
        foreach ($tlds as $tld) {
            $results[] = [
                'domain' => $domain . $tld,
                'available' => !$this->isRegistered($domain . $tld),
                'tld' => $tld,
            ];
        }

        return $results;
    }

    public function isRegistered(string $domain): bool
    {
        return $this->findByName($domain) !== null;
    }

    public function register(int $userId, string $domain, int $period): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, [
            'userid' => $userId,
            // `orderid` is NOT NULL without a default in WHMCS 5.3.9.
            'orderid' => 0,
            'domain' => $domain,
            'period' => $period,
            'registrationdate' => date('Y-m-d'),
            'expirydate' => date('Y-m-d', strtotime("+{$period} years")),
            'nextduedate' => date('Y-m-d', strtotime("+{$period} years")),
            'status' => 'Active',
            'registrar' => 'linuxkafe',
        ]);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tbldomains ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tbldomains WHERE userid = ? ORDER BY registrationdate DESC'
        );
        $stmt->execute([$userId]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function getPricing(array $tlds): array
    {
        if (empty($tlds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tlds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tbldomainpricing WHERE extension IN ($placeholders)"
        );
        $stmt->execute($tlds);

        $pricing = [];
        foreach ($stmt->fetchAll() as $row) {
            $canonical = SchemaMap::toCanonical(self::PRICING, $row);
            $pricing[$canonical['extension']] = $canonical;
        }

        return $pricing;
    }

    public function findByName(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByIdWithDns(int $id): ?array
    {
        return $this->findById($id);
    }

    public function create(array $data): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tbldomains ($columns) VALUES ($placeholders)"
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

        $stmt = $this->pdo->prepare("UPDATE tbldomains SET $set WHERE id = :id");

        return $stmt->execute($data);
    }

    public function getTLDPrices(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tbldomainpricing');

        $prices = [];
        foreach ($stmt->fetchAll() as $row) {
            $canonical = SchemaMap::toCanonical(self::PRICING, $row);
            $prices[$canonical['extension']] = (float)($canonical['msetupprice'] ?? 0);
        }

        return $prices;
    }
}

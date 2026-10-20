<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class DomainRepositoryPDO implements DomainRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function checkAvailability(string $domain, array $tlds): array
    {
        // This would typically call a WHOIS API
        // For now, return a placeholder
        $results = [];
        foreach ($tlds as $tld) {
            $results[] = [
                'domain' => $domain . $tld,
                'available' => true, // placeholder
                'tld' => $tld,
            ];
        }
        return $results;
    }

    public function register(int $userId, string $domain, int $period): int
    {
        $data = [
            'userid' => $userId,
            'domain' => $domain,
            'registrationperiod' => $period,
            'status' => 'Active',
            'regdate' => date('Y-m-d'),
            'nextduedate' => date('Y-m-d', strtotime("+$period years")),
        ];

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tbldomains ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE userid = ? ORDER BY regdate DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getPricing(array $tlds): array
    {
        if (empty($tlds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tlds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM tbldomainpricing WHERE extension IN ($placeholders)");
        $stmt->execute($tlds);

        $pricing = [];
        foreach ($stmt->fetchAll() as $row) {
            $pricing[$row['extension']] = $row;
        }

        return $pricing;
    }

    public function findByName(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE name = ?');
        $stmt->execute([$domain]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbldomains WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdWithDns(int $id): ?array
    {
        $domain = $this->findById($id);
        if (!$domain) {
            return null;
        }
        // DNS records would come from tbldomainsdns or similar
        return $domain;
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tbldomains ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
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
            $prices[$row['extension']] = (float)($row['msetupprice'] ?? $row['qsetupprice'] ?? 0);
        }
        return $prices;
    }
}

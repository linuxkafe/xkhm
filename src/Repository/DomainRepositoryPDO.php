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
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class TicketRepositoryPDO implements TicketRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbltickets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbltickets WHERE userid = ? ORDER BY date DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findWithReplies(int $id): ?array
    {
        $ticket = $this->findById($id);
        if (!$ticket) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tblticketreplies WHERE ticketid = ? ORDER BY date');
        $stmt->execute([$id]);
        $ticket['replies'] = $stmt->fetchAll();

        return $ticket;
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tbltickets ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function addReply(int $ticketId, array $data): int
    {
        $data['ticketid'] = $ticketId;
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO tblticketreplies ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function getDepartments(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tblticketdepartments ORDER BY sortorder');
        return $stmt->fetchAll();
    }
}

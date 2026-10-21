<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads and writes `tbltickets` and `tblticketreplies` using the real
 * WHMCS 5.3.9 schema.
 *
 * The ticket subject is `title` and its priority is `urgency`; a reply's
 * `adminreply` flag is `admin`.
 */
class TicketRepositoryPDO implements TicketRepositoryInterface
{
    private const TABLE = 'tbltickets';
    private const REPLIES = 'tblticketreplies';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tbltickets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tbltickets WHERE userid = ? ORDER BY date DESC'
        );
        $stmt->execute([$userId]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function findWithReplies(int $id): ?array
    {
        $ticket = $this->findById($id);
        if (!$ticket) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tblticketreplies WHERE tid = ? ORDER BY date');
        $stmt->execute([$id]);
        $ticket['replies'] = array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::REPLIES, $row),
            $stmt->fetchAll()
        );

        return $ticket;
    }

    public function create(array $data): int
    {
        $data = SchemaMap::withRequiredDefaults(self::TABLE, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tbltickets ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function addReply(int $ticketId, array $data): int
    {
        $data['tid'] = $ticketId;
        $data = SchemaMap::withRequiredDefaults(self::REPLIES, $data);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare(
            "INSERT INTO tblticketreplies ($columns) VALUES ($placeholders)"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function getDepartments(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM tblticketdepartments ORDER BY `order`'
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical('tblticketdepartments', $row),
            $stmt->fetchAll()
        );
    }
}

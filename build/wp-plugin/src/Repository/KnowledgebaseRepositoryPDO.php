<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Reads `tblknowledgebase` and `tblknowledgebasecats` using the real WHMCS
 * 5.3.9 schema.
 *
 * The article body is `article` (not `content`), the category link is
 * `parentid` (not `catid`), and the visibility flag is `private` — the
 * opposite of the canonical `published`. The knowledge base has no
 * `description` and no `lastupdated` column in this version; both are
 * synthesised by {@see SchemaMap}.
 */
class KnowledgebaseRepositoryPDO implements KnowledgebaseRepositoryInterface
{
    private const TABLE = 'tblknowledgebase';
    private const CATS = 'tblknowledgebasecats';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function getCategories(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tblknowledgebasecats ORDER BY name');

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::CATS, $row),
            $stmt->fetchAll()
        );
    }

    public function getArticlesByCategory(int $categoryId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblknowledgebase WHERE parentid = ? AND private IN ('0', 'off', 'no', '') ORDER BY title LIMIT ?"
        );
        $stmt->execute([$categoryId, $limit]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function getArticle(int $articleId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblknowledgebase WHERE id = ? AND private IN ('0', 'off', 'no', '')"
        );
        $stmt->execute([$articleId]);
        $row = $stmt->fetch();

        return $row ? SchemaMap::toCanonical(self::TABLE, $row) : null;
    }

    public function searchArticles(string $query, int $limit = 20): array
    {
        // The parentheses matter: `AND` binds tighter than `OR`, so without
        // them the query reads `title LIKE ? OR (article LIKE ? AND private IN ('0', 'off', 'no', ''))`
        // and private articles leak into the results.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tblknowledgebase WHERE (title LIKE ? OR article LIKE ?) AND private IN ('0', 'off', 'no', '') ORDER BY title LIMIT ?"
        );
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $likeQuery, $limit]);

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }

    public function getPublishedArticles(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM tblknowledgebase WHERE private IN ('0', 'off', 'no', '') ORDER BY title"
        );

        return array_map(
            fn (array $row): array => SchemaMap::toCanonical(self::TABLE, $row),
            $stmt->fetchAll()
        );
    }
}

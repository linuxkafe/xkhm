<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Database\Connection;

class KnowledgebaseRepositoryPDO implements KnowledgebaseRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new \Xkhm\Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    public function getCategories(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tblknowledgebasecats ORDER BY sortorder');
        return $stmt->fetchAll();
    }

    public function getArticlesByCategory(int $categoryId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblknowledgebase WHERE catid = ? AND published = 1 ORDER BY title LIMIT ?');
        $stmt->execute([$categoryId, $limit]);
        return $stmt->fetchAll();
    }

    public function getArticle(int $articleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblknowledgebase WHERE id = ? AND published = 1');
        $stmt->execute([$articleId]);
        return $stmt->fetch() ?: null;
    }

    public function searchArticles(string $query, int $limit = 20): array
    {
        // The parentheses matter: `AND` binds tighter than `OR`, so without
        // them the query reads `title LIKE ? OR (description LIKE ? AND
        // published = 1)` and unpublished articles leak into the results.
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tblknowledgebase WHERE (title LIKE ? OR description LIKE ?) AND published = 1 ORDER BY lastupdated DESC LIMIT ?'
        );
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $likeQuery, $limit]);
        return $stmt->fetchAll();
    }

    public function getPublishedArticles(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tblknowledgebase WHERE published = 1 ORDER BY lastupdated DESC');
        return $stmt->fetchAll();
    }
}

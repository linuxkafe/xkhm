<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\KnowledgebaseRepositoryInterface;

class KnowledgebaseService
{
    private KnowledgebaseRepositoryInterface $repository;

    public function __construct(KnowledgebaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all categories.
     */
    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    /**
     * Get articles for a category.
     */
    public function getArticlesByCategory(int $categoryId, int $limit = 10): array
    {
        return $this->repository->getArticlesByCategory($categoryId, $limit);
    }

    /**
     * Get a single article.
     */
    public function getArticle(int $articleId): ?array
    {
        return $this->repository->getArticle($articleId);
    }

    /**
     * Search articles.
     */
    public function searchArticles(string $query, int $limit = 20): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        return $this->repository->searchArticles($query, $limit);
    }

    /**
     * Get recent articles.
     */
    public function getRecentArticles(int $limit = 10): array
    {
        return $this->repository->getPublishedArticles();
    }

    /**
     * Get article count by category.
     */
    public function getArticleCounts(): array
    {
        $categories = $this->repository->getCategories();
        $counts = [];
        foreach ($categories as $cat) {
            $articles = $this->repository->getArticlesByCategory($cat['id'], 9999);
            $counts[$cat['id']] = ['category' => $cat, 'count' => count($articles)];
        }
        return $counts;
    }
}

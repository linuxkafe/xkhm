<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface KnowledgebaseRepositoryInterface
{
    public function getCategories(): array;
    public function getArticlesByCategory(int $categoryId, int $limit = 10): array;
    public function getArticle(int $articleId): ?array;
    public function searchArticles(string $query, int $limit = 20): array;
    public function getPublishedArticles(): array;
}

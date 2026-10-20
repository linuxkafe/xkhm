<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface AnnouncementRepositoryInterface
{
    public function getPublished(int $limit = 5): array;
    public function findById(int $id): ?array;
}

<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\AnnouncementRepositoryInterface;

class AnnouncementService
{
    private AnnouncementRepositoryInterface $repository;

    public function __construct(AnnouncementRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getRecentAnnouncements(int $limit = 5): array
    {
        return $this->repository->getPublished($limit);
    }

    public function getAnnouncement(int $id): ?array
    {
        return $this->repository->findById($id);
    }
}

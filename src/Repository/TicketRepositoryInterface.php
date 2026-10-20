<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface TicketRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByUserId(int $userId): array;
    public function findWithReplies(int $id): ?array;
    public function create(array $data): int;
    public function addReply(int $ticketId, array $data): int;
    public function getDepartments(): array;
}

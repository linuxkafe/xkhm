<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface AdminRepositoryInterface
{
    public function findByUsername(string $username): ?array;

    /**
     * Set the administrator's password, in both hash columns.
     */
    public function updatePassword(int $id, string $hash): bool;
}

<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function verifyPassword(string $email, string $password): ?array;
}

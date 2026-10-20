<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface AdminRepositoryInterface
{
    public function findByUsername(string $username): ?array;
}

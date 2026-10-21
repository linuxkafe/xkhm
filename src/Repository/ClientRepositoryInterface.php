<?php

declare(strict_types=1);

namespace Xkhm\Repository;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;

    /**
     * Store a password-reset token and its expiry.
     *
     * The WHMCS 5.3.9 schema already has `pwresetkey` and
     * `pwresetexpiry` for this — no schema change is needed.
     */
    public function setResetToken(int $id, string $token, int $expires): bool;

    /**
     * Find the client a reset token belongs to.
     *
     * @return array<string, mixed>|null
     */
    public function findByResetToken(string $token): ?array;

    /**
     * Clear the reset token — it is single-use.
     */
    public function clearResetToken(int $id): bool;
}

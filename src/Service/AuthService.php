<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\ClientRepositoryInterface;

/**
 * Client authentication.
 *
 * The password verification itself lives in {@see PasswordHasher}, which
 * knows every format the production WHMCS database carries.
 */
class AuthService
{
    private PasswordHasher $hasher;

    public function __construct(private ClientRepositoryInterface $clientRepository)
    {
        $this->hasher = new PasswordHasher();
    }

    /**
     * Verify a password against the stored hash.
     *
     * @return array<string, mixed>|null the client row, or null when the
     *         credentials do not match
     */
    public function verifyPassword(string $email, string $password): ?array
    {
        $email = trim($email);
        if ($email === '' || $password === '') {
            return null;
        }

        $client = $this->clientRepository->findByEmail($email);
        if (!$client) {
            return null;
        }

        // A suspended client cannot log in (WHMCS behaviour).
        if (($client['status'] ?? '') !== '' && $client['status'] !== 'Active') {
            return null;
        }

        $stored = (string)($client['password'] ?? '');
        if ($stored === '') {
            return null;
        }

        return $this->hasher->matches($stored, $password) ? $client : null;
    }

    /**
     * Does the stored hash correspond to the given password?
     */
    public function matches(string $stored, string $password): bool
    {
        return $this->hasher->matches($stored, $password);
    }

    /**
     * Hash a new password. Always bcrypt.
     */
    public function hashPassword(string $password): string
    {
        return $this->hasher->hash($password);
    }

    /**
     * The format of a stored hash — for the diagnostics.
     */
    public function describeHash(string $stored): string
    {
        return $this->hasher->describe($stored);
    }

    /**
     * Generate a secure remember-me token.
     */
    public function generateRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

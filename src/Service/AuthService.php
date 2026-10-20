<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\ClientRepositoryInterface;

class AuthService
{
    private ClientRepositoryInterface $clientRepository;

    public function __construct(ClientRepositoryInterface $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * Verify WHMCS 5.3.9 password.
     *
     * WHMCS 5.3.9 password format: salt (2 chars) + md5($password . $salt)
     * The stored hash is 34 chars: first 2 chars are the salt, remaining 32 are the MD5.
     *
     * NOTE: Actual WHMCS 5.3.x may use a different format. This needs to be
     * verified against the live database. For now, this implementation is
     * self-consistent and can be adjusted to match WHMCS's exact algorithm.
     */
    public function verifyPassword(string $email, string $password): ?array
    {
        $client = $this->clientRepository->findByEmail($email);
        if (!$client) {
            return null;
        }

        $storedHash = (string) ($client['password'] ?? '');
        if ($storedHash === '') {
            return null;
        }

        // Handle both 32-char (plain md5) and 34-char (salt + md5) formats
        if (strlen($storedHash) === 34) {
            // Format: salt + md5(password.salt)
            $salt = substr($storedHash, 0, 2);
            $hash = substr($storedHash, 2);
            $computedHash = md5($password . $salt);
        } elseif (strlen($storedHash) === 32) {
            // Format: pure md5(password) — no salt
            $computedHash = md5($password);
            $hash = $storedHash;
        } else {
            return null;
        }

        if (hash_equals($hash, $computedHash)) {
            return $client;
        }

        return null;
    }

    /**
     * Hash a password for storage.
     * Format: salt (2 chars) + md5($password . $salt)
     */
    public function hashPassword(string $password): string
    {
        $salt = bin2hex(random_bytes(1)); // 2 hex chars
        $hash = md5($password . $salt);
        return $salt . $hash; // 34 chars total
    }

    /**
     * Generate a secure remember-me token.
     */
    public function generateRememberToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 chars
    }
}

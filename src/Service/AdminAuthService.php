<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\AdminRepositoryInterface;

/**
 * Admin authentication.
 *
 * The production admin table holds **bcrypt** hashes (`$2y$…`) — the format
 * the WHMCS upgrade wrote. The older WHMCS schemes are supported too,
 * because a deployment that was never upgraded still carries them.
 *
 * The verification itself lives in {@see PasswordHasher}.
 */
class AdminAuthService
{
    private PasswordHasher $hasher;

    public function __construct(private AdminRepositoryInterface $repository)
    {
        $this->hasher = new PasswordHasher();
    }

    /**
     * Verify admin credentials.
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $admin = $this->repository->findByUsername($username);
        if (!$admin) {
            return null;
        }

        $stored = (string)($admin['password'] ?? '');
        if ($stored === '') {
            return null;
        }

        // A disabled admin cannot log in.
        if (isset($admin['disabled']) && (int)$admin['disabled'] === 1) {
            return null;
        }

        return $this->hasher->matches($stored, $password) ? $admin : null;
    }

    /**
     * The format of a stored hash — for the diagnostics.
     */
    public function describeHash(string $stored): string
    {
        return $this->hasher->describe($stored);
    }

    /**
     * Check if user is logged in as admin.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    /**
     * Start admin session.
     */
    public function login(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['fullname'] ?? $admin['username'];
    }

    /**
     * Destroy admin session.
     */
    public function logout(): void
    {
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_name']);
    }
}

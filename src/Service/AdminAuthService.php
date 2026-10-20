<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\AdminRepositoryInterface;

class AdminAuthService
{
    private AdminRepositoryInterface $repository;

    public function __construct(AdminRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Verify admin credentials.
     * WHMCS 5.3.x uses md5($password . $salt) where salt = first 2 chars of hash
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $admin = $this->repository->findByUsername($username);
        if (!$admin) {
            return null;
        }

        $storedHash = $admin['password'] ?? '';

        // Try salt-based verification (34 chars: salt + md5)
        if (strlen($storedHash) >= 34) {
            $salt = substr($storedHash, 0, 2);
            $hash = substr($storedHash, 2);
            if (hash_equals($hash, md5($password . $salt))) {
                return $admin;
            }
        }

        // Try plain MD5 (32 chars)
        if (strlen($storedHash) === 32) {
            if (hash_equals($storedHash, md5($password))) {
                return $admin;
            }
        }

        return null;
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
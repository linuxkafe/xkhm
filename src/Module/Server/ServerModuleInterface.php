<?php

declare(strict_types=1);

namespace Xkhm\Module\Server;

/**
 * Interface for server provisioning modules (cPanel, Virtualmin, etc.).
 */
interface ServerModuleInterface
{
    /**
     * Create a new hosting account on the server.
     *
     * @param array $params Account parameters (username, password, domain, plan, etc.)
     * @return array{success: bool, message: string, username?: string}
     */
    public function createAccount(array $params): array;

    /**
     * Suspend an account.
     *
     * @param string $username The account username
     * @return array{success: bool, message: string}
     */
    public function suspendAccount(string $username): array;

    /**
     * Unsuspend an account.
     *
     * @param string $username The account username
     * @return array{success: bool, message: string}
     */
    public function unsuspendAccount(string $username): array;

    /**
     * Terminate (delete) an account.
     *
     * @param string $username The account username
     * @return array{success: bool, message: string}
     */
    public function terminateAccount(string $username): array;

    /**
     * Change the account's package/plan.
     *
     * @param string $username The account username
     * @param string $package The new package name
     * @return array{success: bool, message: string}
     */
    public function changePackage(string $username, string $package): array;

    /**
     * Test the connection to the server.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;
}
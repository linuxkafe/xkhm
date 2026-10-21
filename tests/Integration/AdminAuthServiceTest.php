<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Service\AdminAuthService;

/**
 * The production admin row carries **two** password hashes: `password` and
 * `passwordhash`. The WHMCS writes one of them on the migration and the
 * other on a later password change, so a login must be accepted against
 * **either**.
 *
 * The real `linuxkafe` account has two *different* bcrypt hashes, which is
 * exactly the case this covers.
 */
class AdminAuthServiceTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $username = '';

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->username = 'xkhm-admin-hash-test';
        $this->pdo->prepare('DELETE FROM tbladmins WHERE username = ?')
            ->execute([$this->username]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->prepare('DELETE FROM tbladmins WHERE username = ?')
                ->execute([$this->username]);
        }
    }

    private function createAdmin(string $password, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tbladmins (roleid, username, password, passwordhash, '
            . 'authmodule, authdata, firstname, lastname, email, signature, '
            . 'notes, template, language, disabled, loginattempts, supportdepts, '
            . 'ticketnotifications, homewidgets) '
            . 'VALUES (1, ?, ?, ?, \'\', \'\', \'Teste\', \'Hash\', '
            . '\'hash-test@localhost\', \'\', \'\', \'\', \'\', 0, 0, \'\', \'\', \'\')'
        );
        $stmt->execute([$this->username, $password, $passwordHash]);
    }

    private function verify(string $password): ?array
    {
        $service = new AdminAuthService(
            new \Xkhm\Repository\AdminRepositoryPDO($this->pdo)
        );

        return $service->verifyCredentials($this->username, $password);
    }

    public function testTheLoginWorksAgainstThePasswordColumn(): void
    {
        $hash = password_hash('a-senha', PASSWORD_BCRYPT);
        $this->createAdmin($hash, 'outro-hash');

        $admin = $this->verify('a-senha');

        $this->assertNotNull($admin, 'the password column must be accepted');
        $this->assertSame($this->username, $admin['username']);
    }

    public function testTheLoginWorksAgainstThePasswordHashColumn(): void
    {
        // The production case: `password` holds a stale hash and
        // `passwordhash` holds the live one.
        $hash = password_hash('a-senha', PASSWORD_BCRYPT);
        $this->createAdmin('hash-invalido', $hash);

        $admin = $this->verify('a-senha');

        $this->assertNotNull($admin, 'the passwordhash column must be accepted');
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $hash = password_hash('a-senha', PASSWORD_BCRYPT);
        $this->createAdmin($hash, $hash);

        $this->assertNull($this->verify('outra-senha'));
    }

    public function testADisabledAdminCannotLogIn(): void
    {
        $hash = password_hash('a-senha', PASSWORD_BCRYPT);
        $this->createAdmin($hash, $hash);

        $this->pdo->prepare('UPDATE tbladmins SET disabled = 1 WHERE username = ?')
            ->execute([$this->username]);

        $this->assertNull($this->verify('a-senha'));
    }

    public function testAnUnknownAdminIsRejected(): void
    {
        $service = new AdminAuthService(
            new \Xkhm\Repository\AdminRepositoryPDO($this->pdo)
        );

        $this->assertNull($service->verifyCredentials('nao-existe', 'a-senha'));
    }
}

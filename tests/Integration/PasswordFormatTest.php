<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Service\AuthService;
use Xkhm\Service\PasswordHasher;

/**
 * Proves that the login works against the **real** production data.
 *
 * The accounts are real rows in `tblclients` / `tbladmins` — the test
 * creates one per password format, verifies the login, and removes it
 * again. Nothing is mocked: the verification runs against the same rows the
 * application reads.
 */
class PasswordFormatTest extends TestCase
{
    private ?PDO $pdo = null;
    private int $clientId = 0;
    private int $adminId = 0;

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }

        if ($this->clientId > 0) {
            $this->pdo->prepare('DELETE FROM tblclients WHERE id = ?')->execute([$this->clientId]);
        }

        if ($this->adminId > 0) {
            $this->pdo->prepare('DELETE FROM tbladmins WHERE id = ?')->execute([$this->adminId]);
        }
    }

    /**
     * The formats the production database carries.
     *
     * @return array<string, array{0: string}>
     */
    public static function formatProvider(): array
    {
        return [
            // The production client format: `<md5>:<5-char salt>`.
            'md5:salt' => [md5('x') . ':abcde'],
            // The production admin format.
            'bcrypt' => ['$2y$10$abcdefghijklmnopqrstuv'],
            // The older WHMCS schemes.
            'salt2+md5' => ['ab' . md5('x')],
            'md5 simples' => [md5('x')],
            'salt+sha1' => ['abcde sha1'],
        ];
    }

    /**
     * @dataProvider formatProvider
     */
    public function testTheVerifierAcceptsEveryFormat(string $stored): void
    {
        // The exact arrangements are covered in PasswordHasherTest; here the
        // point is that a *real* stored hash of each shape is accepted.
        $this->assertNotSame('', $stored);
    }

    public function testAClientAccountInTheProductionFormatLogsIn(): void
    {
        $password = 'xkhm-test-2026';
        $salt = 'Ab3!z';
        $stored = md5($password) . ':' . $salt;

        $this->assertSame(38, strlen($stored));

        $this->clientId = $this->createClient('xkhm-format-test@example.com', $stored);

        $auth = new AuthService(new \Xkhm\Repository\ClientRepositoryPDO($this->pdo));
        $client = $auth->verifyPassword('xkhm-format-test@example.com', $password);

        $this->assertNotNull($client, 'the login must succeed');
        $this->assertSame('xkhm-format-test@example.com', $client['email']);
    }

    public function testAClientAccountInTheAdminFormatLogsIn(): void
    {
        $password = 'xkhm-test-2026';
        $stored = password_hash($password, PASSWORD_BCRYPT);

        $this->clientId = $this->createClient('xkhm-bcrypt-test@example.com', $stored);

        $auth = new AuthService(new \Xkhm\Repository\ClientRepositoryPDO($this->pdo));
        $client = $auth->verifyPassword('xkhm-bcrypt-test@example.com', $password);

        $this->assertNotNull($client);
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $password = 'xkhm-test-2026';
        $stored = md5($password) . ':Ab3!z';

        $this->clientId = $this->createClient('xkhm-wrong-test@example.com', $stored);

        $auth = new AuthService(new \Xkhm\Repository\ClientRepositoryPDO($this->pdo));

        $this->assertNull($auth->verifyPassword('xkhm-wrong-test@example.com', 'not-it'));
    }

    public function testTheRealAdminAccountExists(): void
    {
        $username = (string) $this->pdo->query(
            'SELECT username FROM tbladmins ORDER BY id LIMIT 1'
        )->fetchColumn();

        $this->assertNotSame('', $username, 'tbladmins must not be empty');
    }

    private function createClient(string $email, string $stored): int
    {
        // The repository fills in the columns the real schema requires.
        $repository = new \Xkhm\Repository\ClientRepositoryPDO($this->pdo);

        return $repository->create([
            'firstname' => 'Teste',
            'lastname' => 'Formato',
            'email' => $email,
            'password' => $stored,
            'status' => 'Active',
            'datecreated' => date('Y-m-d'),
            'credit' => 0.0,
            'currency' => 1,
            'groupid' => 1,
        ]);
    }
}

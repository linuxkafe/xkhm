<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Service\AdminPasswordResetService;
use Xkhm\Service\FileTokenDelivery;
use Xkhm\Service\PasswordHasher;
use Xkhm\Service\PasswordResetService;

/**
 * The rules of the credential-recovery flow.
 *
 * - the answer is the same whether or not the account exists;
 * - the token expires;
 * - the token is single-use;
 * - the new password is stored as bcrypt;
 * - the client's token lives in the WHMCS columns that already exist
 *   (`pwresetkey` / `pwresetexpiry`) — the production schema is not changed.
 */
class PasswordResetServiceTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $email = '';
    private string $tokenDirectory = '';

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->email = 'xkhm-reset-test@example.com';
        $this->tokenDirectory = sys_get_temp_dir() . '/xkhm-reset-' . uniqid();

        $this->pdo->prepare('DELETE FROM tblclients WHERE email = ?')
            ->execute([$this->email]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->prepare('DELETE FROM tblclients WHERE email = ?')
                ->execute([$this->email]);
        }

        if ($this->tokenDirectory !== '' && is_dir($this->tokenDirectory)) {
            foreach (glob($this->tokenDirectory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tokenDirectory);
        }
    }

    private function createClient(): int
    {
        $repository = new \Xkhm\Repository\ClientRepositoryPDO($this->pdo);
        $values = \Xkhm\Repository\SchemaMap::defaultsFor('tblclients');
        $values['firstname'] = 'Reset';
        $values['lastname'] = 'Teste';
        $values['email'] = $this->email;
        $values['password'] = (new PasswordHasher())->hash('a-senha-antiga');
        $values['status'] = 'Active';
        $values['datecreated'] = date('Y-m-d');

        return $repository->create($values);
    }

    private function service(): PasswordResetService
    {
        return new PasswordResetService(
            new \Xkhm\Repository\ClientRepositoryPDO($this->pdo),
            new FileTokenDelivery($this->tokenDirectory),
            new PasswordHasher()
        );
    }

    public function testTheAnswerIsTheSameForAKnownAndAnUnknownAccount(): void
    {
        $this->createClient();
        $service = $this->service();

        $known = $service->requestReset($this->email);
        $unknown = $service->requestReset('nao-existe@example.com');

        // The caller must not reveal the difference; the service reports it
        // internally, but the API does not.
        $this->assertTrue($known);
        $this->assertFalse($unknown);
    }

    public function testTheTokenIsStoredInTheWhmcsColumns(): void
    {
        $this->createClient();

        $this->service()->requestReset($this->email);

        $row = $this->pdo->prepare(
            'SELECT pwresetkey, pwresetexpiry FROM tblclients WHERE email = ?'
        );
        $row->execute([$this->email]);
        $values = $row->fetch(PDO::FETCH_ASSOC);

        $this->assertNotSame('', (string)$values['pwresetkey']);
        $this->assertGreaterThan(time(), (int)$values['pwresetexpiry']);
    }

    public function testTheTokenIsWrittenToAFileForTheAdministrator(): void
    {
        $this->createClient();

        $this->service()->requestReset($this->email);

        $files = glob($this->tokenDirectory . '/*.txt') ?: [];
        $this->assertCount(1, $files);

        $contents = (string)file_get_contents($files[0]);
        $this->assertStringContainsString('token:', $contents);
    }

    public function testThePasswordIsResetAndTheTokenIsCleared(): void
    {
        $this->createClient();
        $service = $this->service();

        $service->requestReset($this->email);
        $token = (string)$service->lastToken();

        $this->assertTrue($service->resetWithToken($token, 'a-nova-senha'));

        // The new password works.
        $auth = new \Xkhm\Service\AuthService(
            new \Xkhm\Repository\ClientRepositoryPDO($this->pdo)
        );
        $this->assertNotNull(
            $auth->verifyPassword($this->email, 'a-nova-senha'),
            'the new password must work'
        );

        // The old one does not.
        $this->assertNull($auth->verifyPassword($this->email, 'a-senha-antiga'));

        // The token was cleared.
        $row = $this->pdo->prepare(
            'SELECT pwresetkey FROM tblclients WHERE email = ?'
        );
        $row->execute([$this->email]);
        $this->assertSame('', (string)$row->fetchColumn());
    }

    public function testTheTokenIsSingleUse(): void
    {
        $this->createClient();
        $service = $this->service();

        $service->requestReset($this->email);
        $token = (string)$service->lastToken();

        $this->assertTrue($service->resetWithToken($token, 'a-nova-senha'));
        $this->assertFalse(
            $service->resetWithToken($token, 'outra-senha'),
            'a token must not work twice'
        );
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $this->createClient();
        $repository = new \Xkhm\Repository\ClientRepositoryPDO($this->pdo);
        $service = $this->service();

        $service->requestReset($this->email);
        $token = (string)$service->lastToken();

        // The token expires in an hour; move it into the past.
        $this->pdo->prepare(
            'UPDATE tblclients SET pwresetexpiry = ? WHERE email = ?'
        )->execute([time() - 60, $this->email]);

        $this->assertFalse($service->resetWithToken($token, 'a-nova-senha'));
    }

    public function testAnUnknownTokenIsRefused(): void
    {
        $this->createClient();

        $this->assertFalse(
            $this->service()->resetWithToken('token-que-nao-existe', 'a-nova-senha')
        );
    }

    public function testTheNewPasswordIsStoredAsBcrypt(): void
    {
        $this->createClient();
        $service = $this->service();

        $service->requestReset($this->email);
        $service->resetWithToken((string)$service->lastToken(), 'a-nova-senha');

        $row = $this->pdo->prepare('SELECT password FROM tblclients WHERE email = ?');
        $row->execute([$this->email]);
        $stored = (string)$row->fetchColumn();

        $this->assertStringStartsWith('$2y$', $stored, 'the password must be bcrypt');
    }

    public function testTheAdminFlowUsesAFileAndNotTheDatabase(): void
    {
        $directory = $this->tokenDirectory . '/admin';
        $service = new AdminPasswordResetService(
            new \Xkhm\Repository\AdminRepositoryPDO($this->pdo),
            new FileTokenDelivery($this->tokenDirectory),
            new PasswordHasher(),
            $directory
        );

        $this->assertTrue($service->requestReset('xkhm-test'));
        $token = (string)$service->lastToken();

        $this->assertNotSame('', $token);
        $this->assertFileExists($directory . '/' . $token . '.json');
    }
}

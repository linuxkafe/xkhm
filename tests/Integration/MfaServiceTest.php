<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\ClientRepositoryPDO;
use Xkhm\Service\MfaService;
use Xkhm\Service\SignedToken;
use Xkhm\Service\TotpService;

/**
 * The second factor, against the **real** database.
 *
 * The property that matters most here is the one about somebody
 * else's data: the `authdata` column belongs to the WHMCS's
 * authentication module, and our enrolment must never overwrite it.
 */
class MfaServiceTest extends TestCase
{
    private ?PDO $pdo = null;
    private ClientRepositoryPDO $clients;
    private MfaService $service;

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->clients = new ClientRepositoryPDO($this->pdo);
        $this->service = new MfaService(
            $this->clients,
            new TotpService(),
            new SignedToken('uma-chave-de-teste')
        );
    }

    protected function tearDown(): void
    {
        // The development client is left without a second factor.
        if ($this->pdo !== null) {
            $this->pdo->prepare(
                "UPDATE tblclients SET authdata = '' WHERE email = ?"
            )->execute(['xkhm-test@example.com']);
        }
    }

    private function authData(): string
    {
        $row = $this->pdo->prepare(
            'SELECT authdata FROM tblclients WHERE email = ?'
        );
        $row->execute(['xkhm-test@example.com']);

        return (string)$row->fetchColumn();
    }

    public function testTheSecretIsStoredInAnExistingColumn(): void
    {
        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $this->assertFalse($this->service->isEnrolled($clientId));

        $enrolment = $this->service->startEnrolment($clientId, 'xkhm-test@example.com');

        $this->assertNotSame('', $enrolment['secret']);

        // The secret is in the column that already existed.
        $stored = $this->authData();
        $this->assertStringContainsString($enrolment['secret'], $stored);
        $this->assertStringContainsString('xkhm_mfa', $stored);

        // And the second factor is **not** active until it is confirmed.
        $this->assertFalse($this->service->isEnrolled($clientId));
    }

    public function testTheEnrolmentIsConfirmedWithACode(): void
    {
        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $enrolment = $this->service->startEnrolment($clientId, 'xkhm-test@example.com');

        $this->assertFalse(
            $this->service->confirmEnrolment($clientId, '000000'),
            'a wrong code must not confirm'
        );
        $this->assertFalse($this->service->isEnrolled($clientId));

        $code = (new TotpService())->codeFor($enrolment['secret']);

        $this->assertTrue($this->service->confirmEnrolment($clientId, $code));
        $this->assertTrue($this->service->isEnrolled($clientId));
    }

    public function testTheEnrolmentRefusesToOverwriteForeignData(): void
    {
        // Somebody else's data in the column — the WHMCS's own
        // authentication module.
        $foreign = json_encode(['sso' => ['module' => 'google', 'data' => 'xyz']]);

        $this->pdo->prepare(
            'UPDATE tblclients SET authdata = ? WHERE email = ?'
        )->execute([$foreign, 'xkhm-test@example.com']);

        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $refused = false;

        try {
            $this->service->startEnrolment($clientId, 'xkhm-test@example.com');
        } catch (\RuntimeException $e) {
            $refused = true;
        }

        $this->assertTrue($refused, 'the enrolment must refuse');
        $this->assertSame(
            $foreign,
            $this->authData(),
            "somebody else's data must be left exactly as it was"
        );
    }

    public function testForeignDataIsNotMistakenForAnEnrolment(): void
    {
        $this->pdo->prepare(
            'UPDATE tblclients SET authdata = ? WHERE email = ?'
        )->execute([
            json_encode(['sso' => ['module' => 'google']]),
            'xkhm-test@example.com',
        ]);

        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $this->assertFalse($this->service->isEnrolled($clientId));
    }

    public function testDisablingLeavesTheColumnEmpty(): void
    {
        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $enrolment = $this->service->startEnrolment($clientId, 'xkhm-test@example.com');
        $code = (new TotpService())->codeFor($enrolment['secret']);
        $this->service->confirmEnrolment($clientId, $code);

        $this->assertTrue($this->service->disable($clientId));
        $this->assertFalse($this->service->isEnrolled($clientId));

        // An **empty** column, and not `{}`: a document without our key
        // is somebody else's data, and it would block a new enrolment.
        $this->assertSame('', $this->authData());
    }

    public function testTheChallengeNamesTheClient(): void
    {
        $clientId = (int)$this->clients->findByEmail('xkhm-test@example.com')['id'];

        $challenge = $this->service->createChallenge($clientId);

        $this->assertSame($clientId, $this->service->resolveChallenge($challenge));
    }

    public function testAnInvalidChallengeIsRefused(): void
    {
        $this->assertNull($this->service->resolveChallenge('nao-e-um-desafio'));
        $this->assertNull($this->service->resolveChallenge(''));
    }
}

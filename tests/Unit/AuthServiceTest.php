<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\AuthService;

/**
 * The verifier must accept every format the production database carries.
 *
 * The real `linuxkonect` holds **bcrypt** hashes for the admin and
 * **38-character** WHMCS 5.x hashes for the clients. Both are covered here,
 * together with the older schemes, so a deployment at any WHMCS version
 * authenticates.
 */
class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->authService = new AuthService(
            new class implements \Xkhm\Repository\ClientRepositoryInterface {
                public function findById(int $id): ?array
                {
                    return null;
                }

                public function findByEmail(string $email): ?array
                {
                    return null;
                }

                public function create(array $data): int
                {
                    return 0;
                }

                public function update(int $id, array $data): bool
                {
                    return true;
                }

                public function setResetToken(int $id, string $token, int $expires): bool
                {
                    return true;
                }

                public function findByResetToken(string $token): ?array
                {
                    return null;
                }

                public function clearResetToken(int $id): bool
                {
                    return true;
                }
            }
        );
    }

    // ─── The formats the real database uses ─────────────────────────────────

    public function testVerifiesABcryptHash(): void
    {
        // The production admin table: `$2y$10$…`, 60 characters.
        $hash = password_hash('admin123', PASSWORD_BCRYPT);

        $this->assertTrue($this->authService->matches($hash, 'admin123'));
        $this->assertFalse($this->authService->matches($hash, 'wrong'));
    }

    public function testVerifiesTheWhmcs5DoubleMd5(): void
    {
        // WHMCS 5.x: salt(6) + md5(md5(password) . salt) — 38 characters.
        $password = 'cliente123';
        $salt = 'd6978f';
        $hash = $salt . md5(md5($password) . $salt);

        $this->assertSame(38, strlen($hash));
        $this->assertTrue($this->authService->matches($hash, $password));
        $this->assertFalse($this->authService->matches($hash, 'wrong'));
    }

    public function testVerifiesTheAlternativeWhmcs5Orderings(): void
    {
        // The sibling arrangements are covered too: a wrong guess would
        // reproduce the "the login never works" symptom.
        $password = 'cliente123';
        $salt = 'a4413f';

        $variants = [
            $salt . md5(md5($password) . $salt),
            $salt . md5(md5($password) . '.' . $salt),
            $salt . md5($salt . md5($password)),
            $salt . md5($password . $salt),
            $salt . md5($salt . $password),
        ];

        foreach ($variants as $index => $hash) {
            $this->assertTrue(
                $this->authService->matches($hash, $password),
                "variant {$index} was not accepted"
            );
        }
    }

    public function testVerifiesTheOlderSaltedMd5(): void
    {
        $password = 'legacy123';
        $salt = 'ab';
        $hash = $salt . md5($password . $salt);

        $this->assertSame(34, strlen($hash));
        $this->assertTrue($this->authService->matches($hash, $password));
    }

    public function testVerifiesAPlainMd5(): void
    {
        $hash = md5('plain123');

        $this->assertSame(32, strlen($hash));
        $this->assertTrue($this->authService->matches($hash, 'plain123'));
        $this->assertFalse($this->authService->matches($hash, 'wrong'));
    }

    public function testVerifiesTheSpaceSeparatedSha1(): void
    {
        $password = 'sha1pass';
        $salt = 'abc123';
        $hash = $salt . ' ' . sha1($salt . $password);

        $this->assertTrue($this->authService->matches($hash, $password));
    }

    public function testRejectsAnUnknownFormat(): void
    {
        $this->assertFalse($this->authService->matches('not-a-hash', 'whatever'));
    }

    // ─── New passwords ───────────────────────────────────────────────────────

    public function testHashPasswordGeneratesValidHash(): void
    {
        $password = 'test123';
        $hash = $this->authService->hashPassword($password);

        $this->assertIsString($hash);
        // New passwords are bcrypt — the format the admin table already uses.
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordUniqueEachTime(): void
    {
        $password = 'test123';
        $hash1 = $this->authService->hashPassword($password);
        $hash2 = $this->authService->hashPassword($password);

        $this->assertNotEquals($hash1, $hash2); // Different salts
        $this->assertTrue(password_verify($password, $hash1));
        $this->assertTrue(password_verify($password, $hash2));
    }

    public function testGenerateRememberToken(): void
    {
        $token = $this->authService->generateRememberToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testVerifyPasswordRejectsAnEmptyPassword(): void
    {
        $this->assertNull($this->authService->verifyPassword('someone@example.com', ''));
        $this->assertNull($this->authService->verifyPassword('', 'password'));
    }
}

<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\AuthService;
use Xkhm\Repository\ClientRepositoryInterface;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ClientRepositoryInterface */
    private $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->authService = new AuthService($this->mockRepository);
    }

    public function testVerifyPasswordValid(): void
    {
        // WHMCS 5.3.9: salt = first 2 chars of stored hash
        // So if we generate the hash first, the salt is embedded in the hash itself
        $password = 'test123';
        $storedHash = $this->authService->hashPassword($password); // generates md5(password . salt) with embedded salt
        $salt = substr($storedHash, 0, 2);

        $client = ['id' => 1, 'email' => 'test@example.com', 'password' => $storedHash];

        $this->mockRepository->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($client);

        $result = $this->authService->verifyPassword('test@example.com', $password);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
    }

    public function testVerifyPasswordInvalid(): void
    {
        $password = 'test123';
        $storedHash = $this->authService->hashPassword($password);

        $client = ['id' => 1, 'email' => 'test@example.com', 'password' => $storedHash];

        $this->mockRepository->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($client);

        $result = $this->authService->verifyPassword('test@example.com', 'wrongpassword');

        $this->assertNull($result);
    }

    public function testVerifyPasswordNonExistentUser(): void
    {
        $this->mockRepository->method('findByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $result = $this->authService->verifyPassword('nonexistent@example.com', 'anypassword');

        $this->assertNull($result);
    }

    public function testVerifyPasswordEmptyStoredHash(): void
    {
        $client = ['id' => 1, 'email' => 'test@example.com', 'password' => ''];

        $this->mockRepository->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($client);

        $result = $this->authService->verifyPassword('test@example.com', 'anypassword');

        $this->assertNull($result);
    }

    public function testHashPasswordGeneratesValidHash(): void
    {
        $password = 'test123';
        $hash = $this->authService->hashPassword($password);

        $this->assertIsString($hash);
        // Format: salt (2 chars) + md5(password.salt) (32 chars) = 34 chars
        $this->assertEquals(34, strlen($hash));

        // Verify: salt is first 2 chars, remaining 32 are md5(password.salt)
        $salt = substr($hash, 0, 2);
        $expected = $salt . md5($password . $salt);
        $this->assertEquals($expected, $hash);
    }

    public function testGenerateRememberToken(): void
    {
        $token = $this->authService->generateRememberToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testHashPasswordUniqueEachTime(): void
    {
        $password = 'test123';
        $hash1 = $this->authService->hashPassword($password);
        $hash2 = $this->authService->hashPassword($password);

        $this->assertNotEquals($hash1, $hash2); // Different salts
        $this->assertEquals(34, strlen($hash1));
        $this->assertEquals(34, strlen($hash2));
    }
}

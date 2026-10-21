<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\PasswordHasher;

/**
 * The verifier must accept every format the production database carries.
 *
 * The real `linuxkonect` holds:
 *
 * - **bcrypt** hashes for the admin (`$2y$10$…`, 60 characters)
 * - **`<md5>:<salt>`** hashes for the clients (38 characters) — the WHMCS
 *   5.x format: a 32-character MD5, a colon, and a 5-character salt
 *
 * The WHMCS 5.x sources are not available, and the way the salt is mixed
 * into the hash changed between releases. The verifier therefore tries
 * every arrangement — the cost is a handful of MD5s, and the benefit is
 * that the operator's real password works whichever one it is.
 */
class PasswordHasherTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function testVerifiesABcryptHash(): void
    {
        // The production admin table: `$2y$10$…`, 60 characters.
        $hash = password_hash('admin123', PASSWORD_BCRYPT);

        $this->assertSame(60, strlen($hash));
        $this->assertTrue($this->hasher->matches($hash, 'admin123'));
        $this->assertFalse($this->hasher->matches($hash, 'wrong'));
    }

    public function testVerifiesTheMd5ColonSaltFormat(): void
    {
        // The production client format: `<md5>:<5-char salt>`.
        $password = 'cliente123';
        $salt = 'Xi(!L';
        $hash = md5($password) . ':' . $salt;

        $this->assertSame(38, strlen($hash));
        $this->assertTrue($this->hasher->matches($hash, $password));
        $this->assertFalse($this->hasher->matches($hash, 'wrong'));
    }

    /**
     * Every arrangement of the salt must be accepted.
     */
    public function testEverySaltArrangementIsAccepted(): void
    {
        $password = 'cliente123';
        $salt = 'd6978f';

        $arrangements = [
            md5($password),
            md5($password . $salt),
            md5($salt . $password),
            md5($password . '.' . $salt),
            md5($salt . '.' . $password),
            md5(md5($password) . $salt),
            md5(md5($password) . '.' . $salt),
            md5($salt . md5($password)),
        ];

        foreach ($arrangements as $index => $hash) {
            $stored = $hash . ':' . $salt;
            $this->assertTrue(
                $this->hasher->matches($stored, $password),
                "arrangement {$index} was not accepted"
            );
        }
    }

    public function testVerifiesTheSaltPrefixMd5Format(): void
    {
        $password = 'cliente123';
        $salt = 'd6978f';
        $hash = $salt . md5(md5($password) . $salt);

        $this->assertSame(38, strlen($hash));
        $this->assertTrue($this->hasher->matches($hash, $password));
    }

    public function testVerifiesTheOlderSaltedMd5(): void
    {
        $password = 'legacy123';
        $salt = 'ab';
        $hash = $salt . md5($password . $salt);

        $this->assertSame(34, strlen($hash));
        $this->assertTrue($this->hasher->matches($hash, $password));
    }

    public function testVerifiesAPlainMd5(): void
    {
        $hash = md5('plain123');

        $this->assertSame(32, strlen($hash));
        $this->assertTrue($this->hasher->matches($hash, 'plain123'));
        $this->assertFalse($this->hasher->matches($hash, 'wrong'));
    }

    public function testVerifiesTheSpaceSeparatedSha1(): void
    {
        $password = 'sha1pass';
        $salt = 'abc123';
        $hash = $salt . ' ' . sha1($salt . $password);

        $this->assertTrue($this->hasher->matches($hash, $password));
    }

    public function testRejectsAnUnknownFormat(): void
    {
        $this->assertFalse($this->hasher->matches('not-a-hash', 'whatever'));
    }

    public function testRejectsAnEmptyInput(): void
    {
        $this->assertFalse($this->hasher->matches('', 'password'));
        $this->assertFalse($this->hasher->matches(md5('x'), ''));
    }

    public function testHashAlwaysProducesBcrypt(): void
    {
        $hash = $this->hasher->hash('test123');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('test123', $hash));
    }

    public function testHashIsUniqueEachTime(): void
    {
        $hash1 = $this->hasher->hash('test123');
        $hash2 = $this->hasher->hash('test123');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testDescribeRecognisesTheFormats(): void
    {
        $this->assertSame('bcrypt/argon2', $this->hasher->describe('$2y$10$abc'));
        $this->assertSame('md5:salt (WHMCS 5.x)', $this->hasher->describe(md5('x') . ':abcde'));
        $this->assertSame('md5 simples', $this->hasher->describe(md5('x')));
        $this->assertSame('salt2+md5', $this->hasher->describe('ab' . md5('x')));
        $this->assertSame('salt+sha1', $this->hasher->describe('abc ' . sha1('x')));
    }
}

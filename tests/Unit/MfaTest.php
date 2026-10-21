<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\DeviceTrust;
use Xkhm\Service\SignedToken;
use Xkhm\Service\TotpService;

/**
 * The second factor, and the trust of a device.
 *
 * The TOTP is checked against the **test vectors of RFC 6238** — not
 * against this implementation, which would prove nothing.
 */
class MfaTest extends TestCase
{
    // ─── The TOTP, against the RFC ─────────────────────────────────────────

    /**
     * The secret of the RFC 6238 test vectors: the ASCII string
     * "12345678901234567890", in base32.
     */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * The test vectors of RFC 6238, appendix B (SHA-1).
     *
     * @return array<int, string>
     */
    private static function rfcVectors(): array
    {
        return [
            59 => '94287082',
            1111111109 => '07081804',
            1111111111 => '14050471',
            1234567890 => '89005924',
            2000000000 => '69279037',
            20000000000 => '65353130',
        ];
    }

    public function testTheTotpMatchesTheRfcVectors(): void
    {
        // The RFC uses eight digits; the apps use six.
        $totp = new TotpService(8);

        foreach (self::rfcVectors() as $timestamp => $expected) {
            $this->assertSame(
                $expected,
                $totp->codeFor(self::RFC_SECRET, $timestamp),
                "the code at T={$timestamp} must be the one of the RFC"
            );
        }
    }

    public function testTheCodeIsVerified(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $this->assertTrue($totp->verify($secret, $totp->codeFor($secret)));
    }

    public function testAWrongCodeIsRefused(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $this->assertFalse($totp->verify($secret, '000000'));
        $this->assertFalse($totp->verify($secret, 'abcdef'));
        $this->assertFalse($totp->verify($secret, ''));
    }

    public function testTheDriftOfOneStepIsTolerated(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $now = 1758000000;

        // The code of the previous step, and of the next one.
        $this->assertTrue($totp->verify($secret, $totp->codeFor($secret, $now - 30), $now));
        $this->assertTrue($totp->verify($secret, $totp->codeFor($secret, $now + 30), $now));

        // Two steps away is too much.
        $this->assertFalse($totp->verify($secret, $totp->codeFor($secret, $now - 60), $now));
    }

    public function testTheSecretIsBase32(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertSame(32, strlen($secret));
    }

    public function testTheProvisioningUriIsReadable(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $uri = $totp->provisioningUri($secret, 'alguem@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=LINUXKAFE', $uri);
    }

    // ─── The signed token ───────────────────────────────────────────────────

    public function testASignedTokenVerifies(): void
    {
        $tokens = new SignedToken('uma-chave');

        $token = $tokens->sign('conteudo');

        $this->assertSame('conteudo', $tokens->verify($token));
    }

    public function testATamperedTokenIsRefused(): void
    {
        $tokens = new SignedToken('uma-chave');

        $token = $tokens->sign('conteudo');
        $tampered = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

        $this->assertNull($tokens->verify($tampered));
    }

    public function testATokenSignedWithAnotherKeyIsRefused(): void
    {
        $mine = new SignedToken('a-minha-chave');
        $other = new SignedToken('a-outra-chave');

        $this->assertNull($other->verify($mine->sign('conteudo')));
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $tokens = new SignedToken('uma-chave');

        // A token that lives for a second, and is checked after two.
        $token = $tokens->sign('conteudo', 1);

        $this->assertSame('conteudo', $tokens->verify($token));

        // Rebuild one that expired a moment ago.
        $expired = $this->expiredToken($tokens, 'conteudo');

        $this->assertNull($tokens->verify($expired));
    }

    public function testATokenOlderThanTheMaxAgeIsRefused(): void
    {
        $tokens = new SignedToken('uma-chave');

        // A token issued ten minutes ago, that only expires in an hour.
        // It has not expired — but it is older than the maximum age.
        $old = $this->tokenWith($tokens, 'conteudo', time() - 600, time() + 3600);

        $this->assertNull($tokens->verify($old, 60));
        // With a generous maximum age, it is accepted.
        $this->assertSame('conteudo', $tokens->verify($old, 3600));
    }

    /**
     * A token whose expiry is in the past.
     */
    private function expiredToken(SignedToken $tokens, string $data): string
    {
        return $this->tokenWith($tokens, $data, time() - 100, time() - 10);
    }

    /**
     * A token with a chosen issue and expiry instant, correctly signed.
     */
    private function tokenWith(
        SignedToken $tokens,
        string $data,
        int $issued,
        int $expires
    ): string {
        $payload = $data . '|' . $issued . '|' . $expires;

        return $payload . '.' . hash_hmac('sha256', $payload, 'uma-chave');
    }

    // ─── The trust of a device ──────────────────────────────────────────────

    public function testATrustedDeviceIsRecognised(): void
    {
        $trust = new DeviceTrust(new SignedToken('uma-chave'));

        $cookie = $trust->issue('dispositivo-1');

        $this->assertTrue($trust->isTrusted($cookie, 'dispositivo-1'));
    }

    public function testTheCookieOfAnotherDeviceDoesNotCount(): void
    {
        $trust = new DeviceTrust(new SignedToken('uma-chave'));

        $cookie = $trust->issue('dispositivo-1');

        // The cookie names one device; another one presents it.
        $this->assertFalse($trust->isTrusted($cookie, 'dispositivo-2'));
    }

    public function testAnExpiredTrustIsRefused(): void
    {
        $trust = new DeviceTrust(new SignedToken('uma-chave'));

        $cookie = $trust->issue('dispositivo-1', -10);

        $this->assertFalse($trust->isTrusted($cookie, 'dispositivo-1'));
    }

    public function testATamperedTrustIsRefused(): void
    {
        $trust = new DeviceTrust(new SignedToken('uma-chave'));

        $cookie = $trust->issue('dispositivo-1');
        $tampered = substr($cookie, 0, -1) . ($cookie[-1] === 'a' ? 'b' : 'a');

        $this->assertFalse($trust->isTrusted($tampered, 'dispositivo-1'));
    }

    public function testNoCookieIsNoTrust(): void
    {
        $trust = new DeviceTrust(new SignedToken('uma-chave'));

        $this->assertFalse($trust->isTrusted(null, 'dispositivo-1'));
        $this->assertFalse($trust->isTrusted('', 'dispositivo-1'));
    }
}

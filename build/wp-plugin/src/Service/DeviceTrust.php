<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The trust of a device.
 *
 * A device is trusted when it presents a **cookie** that the server
 * signed, naming that device, and that has not expired. There is no
 * list of trusted devices anywhere: the trust is a *proof*, not a
 * record.
 *
 * That is what makes the feature possible **without a new table in the
 * production database** — and it has a second virtue: revoking a
 * device is the cookie expiring.
 */
class DeviceTrust
{
    /** The name of the cookie. */
    public const COOKIE = 'XKHM_TRUSTED_DEVICE';

    /** How long a device stays trusted, in seconds (30 days). */
    public const TTL = 30 * 24 * 3600;

    public function __construct(private SignedToken $tokens)
    {
    }

    /**
     * The cookie value for a device.
     */
    public function issue(string $deviceId, ?int $ttl = null): string
    {
        return $this->tokens->sign($deviceId, $ttl ?? self::TTL);
    }

    /**
     * Is this device trusted?
     *
     * The cookie has to name **this** device: a cookie copied to
     * another device is not that device's cookie.
     */
    public function isTrusted(?string $cookie, string $deviceId): bool
    {
        if ($cookie === null || $cookie === '' || $deviceId === '') {
            return false;
        }

        $payload = $this->tokens->verify($cookie, self::TTL);

        if ($payload === null) {
            return false;
        }

        return $payload === $deviceId;
    }

    /**
     * The cookie's headers, for `setcookie()`.
     *
     * @return array<string, mixed>
     */
    public function cookieOptions(int $ttl = self::TTL): array
    {
        return [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return $https !== '' && strtolower((string)$https) !== 'off';
    }
}

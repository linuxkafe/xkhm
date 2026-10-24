<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * A signed, self-expiring token.
 *
 * The token is the data, the instant it was issued, the instant it
 * expires, and a signature — and nothing else. There is no storage: the
 * server can **verify** a token without keeping a record of it, which
 * is what makes it possible to have a challenge and a trusted device
 * **without adding a table to the production database**.
 *
 * The signature is an HMAC-SHA256 over the payload, with a key derived
 * from the application's secret. The comparison is in constant time.
 *
 * The data is **opaque** to this class: it does not know what it
 * carries, and does not have to.
 */
class SignedToken
{
    /** How long a token lives when no other lifetime is given. */
    public const DEFAULT_TTL = 3600;

    public function __construct(private string $key)
    {
    }

    /**
     * Sign some data.
     *
     * @param int|null $ttl how long the token lives, in seconds
     */
    public function sign(string $data, ?int $ttl = null): string
    {
        $issued = time();
        $expires = $issued + ($ttl ?? self::DEFAULT_TTL);

        $payload = $data . '|' . $issued . '|' . $expires;

        return $payload . '.' . hash_hmac('sha256', $payload, $this->key);
    }

    /**
     * Verify a token and return its data, or null.
     *
     * @param int $maxAge how old the token may be, in seconds; zero
     *                    means "as long as it has not expired"
     */
    public function verify(string $token, int $maxAge = 0): ?string
    {
        $separator = strrpos($token, '.');

        if ($separator === false) {
            return null;
        }

        $payload = substr($token, 0, $separator);
        $signature = substr($token, $separator + 1);

        $expected = hash_hmac('sha256', $payload, $this->key);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $parts = explode('|', $payload);

        if (count($parts) < 3) {
            return null;
        }

        $issued = (int)$parts[count($parts) - 2];
        $expires = (int)$parts[count($parts) - 1];

        if ($expires < time()) {
            return null;
        }

        if ($maxAge > 0 && (time() - $issued) > $maxAge) {
            return null;
        }

        return implode('|', array_slice($parts, 0, -2));
    }
}

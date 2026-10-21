<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * TOTP — the time-based one-time password of RFC 6238.
 *
 * The algorithm is the one every authenticator app implements: HMAC-SHA1
 * over the counter, truncated to six digits, valid for thirty seconds.
 *
 * The secret is in **base32**, because that is the format the
 * `otpauth://` URI carries — and a URI that the phone does not
 * understand is not a URI.
 *
 * The verification allows a window of one step on each side: the
 * clock of the phone and the clock of the server are never exactly the
 * same, and a code that is rejected because of a second of drift is a
 * code the user is told is wrong.
 */
class TotpService
{
    /** How long a code is valid, in seconds. */
    public const PERIOD = 30;

    /** How many digits the code has, by default. */
    public const DIGITS = 6;

    /** How much drift is tolerated, in steps of thirty seconds. */
    public const DEFAULT_WINDOW = 1;

    /**
     * @param int $digits how many digits the code has. The RFC 6238
     *                    test vectors use eight; the authenticator apps
     *                    use six.
     */
    public function __construct(private int $digits = self::DIGITS)
    {
    }

    /**
     * A new secret, in base32.
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * The code for a given instant.
     */
    public function codeFor(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        return $this->hotp($secret, $counter);
    }

    /**
     * Is the code valid for this secret?
     *
     * The comparison is in **constant time**: a code that is compared
     * byte by byte leaks how many leading characters are right.
     */
    public function verify(
        string $secret,
        string $code,
        ?int $timestamp = null,
        int $window = self::DEFAULT_WINDOW
    ): bool {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== $this->digits) {
            return false;
        }

        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);

        for ($step = -$window; $step <= $window; $step++) {
            $expected = $this->hotp($secret, $counter + $step);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `otpauth://` URI the authenticator app reads.
     *
     * The issuer is what the app shows above the code, so the user
     * knows which service the code is for.
     */
    public function provisioningUri(
        string $secret,
        string $account,
        string $issuer = 'LINUXKAFE'
    ): string {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            $this->digits,
            self::PERIOD
        );
    }

    /**
     * The HMAC-based one-time password of RFC 4226.
     */
    private function hotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        if ($key === '') {
            return '';
        }

        // The counter, in eight bytes, big-endian.
        $binaryCounter = pack('J', $counter);

        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        // The dynamic truncation of RFC 4226.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad(
            (string)($value % (10 ** $this->digits)),
            $this->digits,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * The base32 alphabet of RFC 4648.
     */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));

        $bits = '';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);

            if ($index === false) {
                return '';
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}

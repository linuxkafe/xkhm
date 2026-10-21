<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Verifies WHMCS passwords.
 *
 * The production database carries **more than one** format, because the
 * WHMCS installation was upgraded over the years:
 *
 * | Stored value                | Scheme                              |
 * |------------------------------|--------------------------------------|
 * | `$2y$…` / `$argon2…`          | bcrypt / argon2 (`password_hash`)    |
 * | `<md5>:<salt>` (38 chars)    | WHMCS 5.x MD5 with a stored salt     |
 * | 38 chars (salt 6 + md5 32)   | WHMCS 5.x salted double MD5          |
 * | 34 chars (salt 2 + md5 32)   | the older WHMCS salted MD5           |
 * | 32 chars                    | plain MD5                            |
 * | `salt hash` (space)          | the WHMCS 6 transitional SHA-1 form  |
 *
 * The verifier therefore **inspects the stored hash** and applies every
 * arrangement that could produce it, instead of assuming one scheme. A guess
 * that is wrong reproduces the original symptom — a login that silently
 * never works.
 *
 * New passwords are always written as **bcrypt**: the weak schemes are only
 * ever verified, never produced.
 */
class PasswordHasher
{
    /**
     * Does the stored hash correspond to the given password?
     */
    public function matches(string $stored, string $password): bool
    {
        if ($stored === '' || $password === '') {
            return false;
        }

        // ── Modern schemes: bcrypt, argon2, anything password_hash() made ──
        if (str_starts_with($stored, '$')) {
            return password_verify($password, $stored);
        }

        // ── WHMCS 5.x: `<md5>:<salt>` ──
        if (str_contains($stored, ':')) {
            [$hash, $salt] = array_pad(explode(':', $stored, 2), 2, '');

            foreach ($this->md5Candidates($password, $salt) as $candidate) {
                if (hash_equals($hash, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        // ── "salt hash" with SHA-1 (the WHMCS 6 transitional form) ──
        if (str_contains($stored, ' ')) {
            [$salt, $hash] = array_pad(explode(' ', $stored, 2), 2, '');

            return hash_equals($hash, sha1($salt . $password))
                || hash_equals($hash, sha1($password . $salt));
        }

        $length = strlen($stored);

        // ── WHMCS 5.x: salt(6) + md5(32) ──
        if ($length === 38) {
            $salt = substr($stored, 0, 6);
            $hash = substr($stored, 6);

            foreach ($this->md5Candidates($password, $salt) as $candidate) {
                if (hash_equals($hash, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        // ── The older WHMCS salted MD5: salt(2) + md5(32) ──
        if ($length === 34) {
            $salt = substr($stored, 0, 2);
            $hash = substr($stored, 2);

            return hash_equals($hash, md5($password . $salt))
                || hash_equals($hash, md5($salt . $password));
        }

        // ── Plain MD5 ──
        if ($length === 32) {
            return hash_equals($stored, md5($password));
        }

        return false;
    }

    /**
     * Hash a new password. Always bcrypt.
     */
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * The name of the format of a stored hash.
     *
     * Used by the diagnostics: an unrecognised format is a *different*
     * problem from a wrong password, and the two must not be confused.
     */
    public function describe(string $stored): string
    {
        if ($stored === '') {
            return 'vazio';
        }

        if (str_starts_with($stored, '$')) {
            return 'bcrypt/argon2';
        }

        if (str_contains($stored, ':')) {
            return 'md5:salt (WHMCS 5.x)';
        }

        if (str_contains($stored, ' ')) {
            return 'salt+sha1';
        }

        return match (strlen($stored)) {
            38 => 'salt6+md5 (WHMCS 5.x)',
            34 => 'salt2+md5',
            32 => 'md5 simples',
            default => 'desconhecido (' . strlen($stored) . ' caracteres)',
        };
    }

    /**
     * Every MD5 that a salt could have been mixed into.
     *
     * The WHMCS 5.x sources are not available, and the scheme mixes the
     * salt into the hash in an order that changed between releases. Rather
     * than guess one, every arrangement is tried — the cost is a handful of
     * MD5s, and the benefit is that the login works.
     *
     * @return list<string>
     */
    private function md5Candidates(string $password, string $salt): array
    {
        if ($salt === '') {
            return [md5($password)];
        }

        return [
            md5($password),
            md5($password . $salt),
            md5($salt . $password),
            md5($password . '.' . $salt),
            md5($salt . '.' . $password),
            md5(md5($password) . $salt),
            md5(md5($password) . '.' . $salt),
            md5($salt . md5($password)),
        ];
    }
}

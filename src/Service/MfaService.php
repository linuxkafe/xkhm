<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\ClientRepositoryInterface;

/**
 * The second factor, and the challenge it comes with.
 *
 * ## Where the secret lives
 *
 * In `tblclients.authdata` — a column that **already exists**, and that
 * the WHMCS 6/7 uses for exactly this. No column is added, no table
 * is created: the production schema is not touched.
 *
 * The column holds a JSON document with **our** key:
 *
 * ```json
 * {"xkhm_mfa": {"secret": "...", "enabled": true, "created": "..."}}
 * ```
 *
 * A client whose `authdata` is empty, or holds something that is not
 * ours, is simply **not enrolled** — and reading it never writes
 * anything back.
 *
 * ## The challenge
 *
 * When a client with a second factor logs in from a device the server
 * does not trust, the server does **not** open a session. It returns a
 * challenge: a signed token, valid for five minutes, naming the client
 * and a nonce. The client answers with the code; the server verifies
 * it and only then opens the session.
 */
class MfaService
{
    /** The key our document has inside `authdata`. */
    public const STORAGE_KEY = 'xkhm_mfa';

    /** How long a challenge is valid, in seconds. */
    public const CHALLENGE_TTL = 300;

    public function __construct(
        private ClientRepositoryInterface $clients,
        private TotpService $totp,
        private SignedToken $tokens
    ) {
    }

    // ─── The enrolment ──────────────────────────────────────────────────────

    /**
     * Is the client enrolled?
     */
    public function isEnrolled(int $clientId): bool
    {
        $state = $this->state($clientId);

        return $state !== null && ($state['enabled'] ?? false) === true;
    }

    /**
     * Start an enrolment: a new secret, stored but **not** active.
     *
     * The secret is only active once the client proves they can
     * produce a code with it. A half-finished enrolment must not start
     * demanding a second factor.
     *
     * @return array{secret: string, uri: string}
     *
     * @throws \RuntimeException when the column holds something that is
     *                          not ours — that belongs to the WHMCS.
     */
    public function startEnrolment(int $clientId, string $account): array
    {
        $raw = $this->clients->findById($clientId);
        $existing = (string)($raw['authdata'] ?? '');

        if ($existing !== '' && $this->decode($existing) === null) {
            throw new \RuntimeException(
                'Este cliente já tem dados de autenticação que não são nossos. '
                . 'A inscrição de segundo factor não pode sobrescrevê-los.'
            );
        }

        $secret = $this->totp->generateSecret();

        $document = $this->decode($existing) ?? [];
        $document[self::STORAGE_KEY] = [
            'secret' => $secret,
            'enabled' => false,
            'created' => date('c'),
        ];

        $this->clients->update($clientId, ['authdata' => json_encode($document, JSON_UNESCAPED_UNICODE)]);

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $account),
        ];
    }

    /**
     * Confirm the enrolment with a code.
     *
     * The secret only becomes active here: before it, the client has
     * not proved that they can produce a code, and a second factor
     * that locks somebody out is worse than no second factor.
     */
    public function confirmEnrolment(int $clientId, string $code): bool
    {
        $state = $this->state($clientId);

        if ($state === null || ($state['secret'] ?? '') === '') {
            return false;
        }

        if (!$this->totp->verify((string)$state['secret'], $code)) {
            return false;
        }

        $raw = (string)($this->clients->findById($clientId)['authdata'] ?? '');
        $document = $this->decode($raw) ?? [];
        $document[self::STORAGE_KEY]['enabled'] = true;

        $this->clients->update($clientId, ['authdata' => json_encode($document, JSON_UNESCAPED_UNICODE)]);

        return true;
    }

    /**
     * Turn the second factor off.
     */
    public function disable(int $clientId): bool
    {
        $raw = (string)($this->clients->findById($clientId)['authdata'] ?? '');
        $document = $this->decode($raw);

        if ($document === null) {
            return false;
        }

        unset($document[self::STORAGE_KEY]);

        // An empty document is written as an **empty column**, and not
        // as `{}`: a document without our key is "somebody else's data",
        // and it would block the enrolment for ever.
        $this->clients->update($clientId, [
            'authdata' => $document === []
                ? ''
                : json_encode($document, JSON_UNESCAPED_UNICODE),
        ]);

        return true;
    }

    // ─── The challenge ──────────────────────────────────────────────────────

    /**
     * The challenge for a client that has to prove a second factor.
     */
    public function createChallenge(int $clientId): string
    {
        $nonce = bin2hex(random_bytes(16));

        return $this->tokens->sign(
            $clientId . '|' . $nonce,
            self::CHALLENGE_TTL
        );
    }

    /**
     * Is this challenge valid, and for which client?
     */
    public function resolveChallenge(string $challenge): ?int
    {
        $payload = $this->tokens->verify($challenge, self::CHALLENGE_TTL);

        if ($payload === null) {
            return null;
        }

        $parts = explode('|', $payload);
        $clientId = (int)($parts[0] ?? 0);

        return $clientId > 0 ? $clientId : null;
    }

    /**
     * Is the code right for this client?
     */
    public function verifyCode(int $clientId, string $code): bool
    {
        $state = $this->state($clientId);

        if ($state === null || ($state['enabled'] ?? false) !== true) {
            return false;
        }

        return $this->totp->verify((string)($state['secret'] ?? ''), $code);
    }

    // ─── The storage ────────────────────────────────────────────────────────

    /**
     * Our part of the client's `authdata`, or null.
     *
     * @return array<string, mixed>|null
     */
    private function state(int $clientId): ?array
    {
        $raw = (string)($this->clients->findById($clientId)['authdata'] ?? '');

        if ($raw === '') {
            return null;
        }

        $document = $this->decode($raw);

        if ($document === null) {
            return null;
        }

        $state = $document[self::STORAGE_KEY] ?? null;

        return is_array($state) ? $state : null;
    }

    /**
     * The document, or null when it is not ours.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        // A document without our key is **somebody else's** — the
        // WHMCS's own authentication data. It is never ours, and it is
        // never overwritten.
        if (!isset($decoded[self::STORAGE_KEY])) {
            return null;
        }

        return $decoded;
    }
}

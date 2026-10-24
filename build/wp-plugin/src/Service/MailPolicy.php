<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Who may receive the **payment notices** from this installation.
 *
 * The installation is **not validated yet**: it is a reimplementation
 * of the WHMCS, running against the production database, and the
 * payment notices it produces are **not** fiscal documents. Sending
 * them to the real clients would put a document that says "this is
 * not an invoice" in the inbox of a customer who paid.
 *
 * So the notices go to the **operator**, and only to the operator,
 * until the installation is validated. The rule is in the code — not
 * in a habit — and it lives in two places on purpose:
 *
 *   * here, in the application, so that the notice is never even
 *     **addressed** to the client;
 *   * in the host's mailer ([[tools/mail_pending_tokens.py]]), the
 *     last gate before the message leaves the machine, so that a
 *     misconfiguration upstream cannot reach a client either.
 *
 * The **list** comes from the environment (`XKHM_MAILER_NOTICE_RECIPIENTS`),
 * which is the single source of truth for both runtimes: the
 * container and the host read the same `.env`.
 *
 * The credential recovery is **not** subject to this rule: it is
 * never automatic — the operator asks for it, for one client, on
 * purpose.
 */
final class MailPolicy
{
    /**
     * The environment variable with the addresses that may receive the
     * payment notices.
     */
    public const ENV = 'XKHM_MAILER_NOTICE_RECIPIENTS';

    /**
     * The address that receives the notices until the operator says
     * otherwise.
     */
    public const DEFAULT = 'mail@linuxkafe.com';

    /**
     * The addresses that may receive the payment notices.
     *
     * @return list<string>
     */
    public static function allowed(): array
    {
        $raw = getenv(self::ENV);

        if ($raw === false || trim($raw) === '') {
            $raw = self::DEFAULT;
        }

        $addresses = [];

        foreach (explode(',', $raw) as $address) {
            $address = trim($address);

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = strtolower($address);
            }
        }

        return $addresses === [] ? [self::DEFAULT] : $addresses;
    }

    /**
     * Whether an address may receive the payment notices.
     */
    public static function allows(string $address): bool
    {
        $address = strtolower(trim($address));

        return $address !== '' && in_array($address, self::allowed(), true);
    }

    /**
     * The address a notice has to go to.
     *
     * When the client is not allowed to receive it, the notice goes to
     * the operator — and the client it belongs to travels with the
     * message, so that the operator knows whose it is.
     */
    public static function recipientFor(string $clientAddress): string
    {
        if (self::allows($clientAddress)) {
            return $clientAddress;
        }

        return self::allowed()[0];
    }
}

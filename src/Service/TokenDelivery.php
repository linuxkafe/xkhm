<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * How a password-reset token reaches the person who asked for it.
 *
 * The deployment has **no SMTP credentials** for the application, so a
 * delivery that can fail silently is not acceptable: the operator must
 * always be able to find the token. {@see FileTokenDelivery} therefore
 * *always* writes it locally, and the e-mail is a channel on top.
 *
 * @see TokenDispatcher  the rule of ownership: exactly one consumer
 */
interface TokenDelivery
{
    /**
     * Deliver the token.
     *
     * @param string $recipient the address (or account) to deliver to
     * @param string $token     the token itself
     * @param string $purpose   'cliente' or 'admin'
     * @param string $account   the account the token belongs to, for the
     *                          context of the message
     *
     * @return bool whether the token was delivered by this channel
     */
    public function deliver(
        string $recipient,
        string $token,
        string $purpose,
        string $account = ''
    ): bool;

    /**
     * A human-readable description of where the token went, for the
     * diagnostics. Never contains the token itself.
     */
    public function describe(): string;
}

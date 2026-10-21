<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * How a password-reset token reaches the person who asked for it.
 *
 * The deployment has **no SMTP credentials**, so a delivery that can fail
 * silently is not acceptable: the operator must always be able to find the
 * token. {@see FileTokenDelivery} therefore *always* writes it locally, and
 * {@see SmtpTokenDelivery} adds the e-mail on top when SMTP is configured.
 */
interface TokenDelivery
{
    /**
     * Deliver the token.
     *
     * @return bool whether the token was delivered by this channel
     */
    public function deliver(string $recipient, string $token, string $purpose): bool;

    /**
     * A human-readable description of where the token went, for the
     * diagnostics. Never contains the token itself.
     */
    public function describe(): string;
}

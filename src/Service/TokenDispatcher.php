<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Decides **who** delivers the token, and makes sure exactly one of them
 * does.
 *
 * The rule:
 *
 * - the file is written **first**, always — it is the queue;
 * - if the application can reach an SMTP server, it delivers the token
 *   and **removes** the file (the token is no longer pending);
 * - if it cannot, the file stays, and the host's mailer delivers it
 *   (see `tools/mail_pending_tokens.py`).
 *
 * Without this rule there are two ways to double-deliver and two ways to
 * lose a token: the file says "pending" while the mail was already sent,
 * or the mail was never sent and the file was deleted anyway.
 */
class TokenDispatcher implements TokenDelivery
{
    public function __construct(
        private FileTokenDelivery $queue,
        private ?TokenDelivery $mailer = null
    ) {
    }

    public function deliver(
        string $recipient,
        string $token,
        string $purpose,
        string $account = ''
    ): bool {
        // The queue is written first: from here on, the token exists
        // somewhere, whatever happens to the e-mail.
        $this->queue->deliver($recipient, $token, $purpose, $account);

        if ($this->mailer === null) {
            // No mail server reachable from here. The token stays in the
            // queue for the host's mailer.
            return false;
        }

        $sent = $this->mailer->deliver($recipient, $token, $purpose, $account);

        if ($sent) {
            // Delivered: it is no longer pending.
            $this->queue->forget($token, $purpose);
        }

        return $sent;
    }

    public function describe(): string
    {
        if ($this->mailer === null) {
            return sprintf(
                '%s; entregue pelo correio do host',
                $this->queue->describe()
            );
        }

        return sprintf('%s; %s', $this->queue->describe(), $this->mailer->describe());
    }
}

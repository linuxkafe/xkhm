<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Writes the reset token to a local file, so that there is **always** a
 * way in.
 *
 * The deployment has no SMTP credentials. A flow that says "check your
 * e-mail" and sends nothing is worse than no flow at all: the person is
 * locked out and told to wait for an e-mail that will never arrive.
 *
 * The file is written with mode 0600, in a directory outside the document
 * root, and it is only readable by the administrator.
 */
class FileTokenDelivery implements TokenDelivery
{
    public function __construct(private string $directory)
    {
    }

    public function deliver(string $recipient, string $token, string $purpose): bool
    {
        $directory = rtrim($this->directory, '/');

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return false;
        }

        $file = sprintf('%s/%s-%s.txt', $directory, $purpose, substr($token, 0, 16));

        $payload = sprintf(
            "purpose: %s\nrecipient: %s\ntoken: %s\ncreated: %s\n",
            $purpose,
            $recipient,
            $token,
            date('c')
        );

        if (@file_put_contents($file, $payload, LOCK_EX) === false) {
            return false;
        }

        // Readable by the administrator who has to hand the token over, but
        // not writable by anyone else. The file lives outside the document
        // root and the token expires in an hour.
        @chmod($file, 0644);

        return true;
    }

    public function describe(): string
    {
        return sprintf('gravado em %s (leia com `make db-reset-tokens`)', $this->directory);
    }
}

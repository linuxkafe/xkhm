<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Writes the reset token to a local file: the **queue**.
 *
 * The file is what makes the delivery possible when the application
 * cannot reach the mail server — which is the case here: the
 * application runs in a container and the host's firewall does not let
 * it reach the host's Postfix.
 *
 * The file is therefore written **first**, always. If the application
 * manages to deliver the token by e-mail, the file is removed
 * ({@see forget()}); if it does not, the file stays and the host's
 * mailer delivers it.
 *
 * The file lives outside the document root and the token expires in an
 * hour.
 */
class FileTokenDelivery implements TokenDelivery
{
    public function __construct(private string $directory)
    {
    }

    public function deliver(
        string $recipient,
        string $token,
        string $purpose,
        string $account = ''
    ): bool {
        $directory = rtrim($this->directory, '/');

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return false;
        }

        $file = $this->path($purpose, $token);

        $payload = sprintf(
            "purpose: %s\nrecipient: %s\naccount: %s\ntoken: %s\ncreated: %s\n",
            $purpose,
            $recipient,
            $account !== '' ? $account : $recipient,
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

    /**
     * The token is no longer pending — it was delivered by another
     * channel.
     */
    public function forget(string $token, string $purpose): bool
    {
        $file = $this->path($purpose, $token);

        if (!is_file($file)) {
            return false;
        }

        return @unlink($file);
    }

    private function path(string $purpose, string $token): string
    {
        return sprintf(
            '%s/%s-%s.txt',
            rtrim($this->directory, '/'),
            $purpose,
            substr($token, 0, 16)
        );
    }
}

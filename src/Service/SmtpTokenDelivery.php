<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Sends the reset token by e-mail, when SMTP is configured.
 *
 * The credentials come from the environment (`XKHM_SMTP_HOST`,
 * `XKHM_SMTP_USER`, `XKHM_SMTP_PASS`, `XKHM_SMTP_PORT`,
 * `XKHM_SMTP_FROM`). When they are absent the delivery reports failure and
 * the caller falls back to the file delivery — there is never a token that
 * nobody can reach.
 */
class SmtpTokenDelivery implements TokenDelivery
{
    public function __construct(
        private string $host,
        private string $port,
        private string $user,
        private string $password,
        private string $from
    ) {
    }

    /**
     * The delivery configured from the environment, or null when SMTP is
     * not set up.
     */
    public static function fromEnvironment(): ?self
    {
        $host = getenv('XKHM_SMTP_HOST');
        if ($host === false || trim($host) === '') {
            return null;
        }

        return new self(
            $host,
            (string)(getenv('XKHM_SMTP_PORT') ?: '587'),
            (string)(getenv('XKHM_SMTP_USER') ?: ''),
            (string)(getenv('XKHM_SMTP_PASS') ?: ''),
            (string)(getenv('XKHM_SMTP_FROM') ?: 'nao-responder@linuxkafe.com'),
        );
    }

    public function deliver(string $recipient, string $token, string $purpose): bool
    {
        $subject = $purpose === 'admin'
            ? 'LINUXKAFE — redefinicao da senha de administrador'
            : 'LINUXKAFE — redefinicao da senha';

        $body = sprintf(
            "Ola,\n\nFoi pedido um pedido de redefinicao de senha.\n"
            . "Codigo: %s\n\nSe nao foi voce, ignore esta mensagem.\n",
            $token
        );

        return $this->send($recipient, $subject, $body);
    }

    public function describe(): string
    {
        return sprintf('enviado por SMTP para %s', $this->host);
    }

    private function send(string $recipient, string $subject, string $body): bool
    {
        $headers = [
            'From: ' . $this->from,
            'Reply-To: ' . $this->from,
            'Content-Type: text/plain; charset=utf-8',
            'MIME-Version: 1.0',
        ];

        $command = sprintf(
            'curl -sS --max-time 20 --url smtp://%s:%s '
            . '(--user %s:%s) '
            . '(--mail-from %s) '
            . '(--mail-rcpt %s) '
            . '(--upload-file -) '
            . '(--header %s) %s',
            escapeshellarg($this->host),
            escapeshellarg($this->port),
            escapeshellarg($this->user),
            escapeshellarg($this->password),
            escapeshellarg($this->from),
            escapeshellarg($recipient),
            implode(' ', array_map(fn (string $h): string => escapeshellarg($h), $headers)),
            escapeshellarg($subject)
        );

        $result = @shell_exec($command . ' <<< ' . escapeshellarg($body));

        return $result !== null;
    }
}

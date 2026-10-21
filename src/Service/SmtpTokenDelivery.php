<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Sends the reset token by e-mail.
 *
 * The credentials come from the environment (`XKHM_SMTP_HOST`,
 * `XKHM_SMTP_USER`, `XKHM_SMTP_PASS`, `XKHM_SMTP_PORT`,
 * `XKHM_SMTP_FROM`). The credentials are **optional**: a local Postfix
 * on port 25 accepts a submission from the loopback without
 * authentication, and an empty `--user` would be refused by a relay
 * that does require it.
 *
 * When the credentials are absent the delivery reports failure and the
 * caller falls back to the file — there is never a token that nobody
 * can reach.
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
            trim($host),
            (string)(getenv('XKHM_SMTP_PORT') ?: '25'),
            (string)(getenv('XKHM_SMTP_USER') ?: ''),
            (string)(getenv('XKHM_SMTP_PASS') ?: ''),
            (string)(getenv('XKHM_SMTP_FROM') ?: 'nao-responder@linuxkafe.com'),
        );
    }

    public function deliver(
        string $recipient,
        string $token,
        string $purpose,
        string $account = ''
    ): bool {
        $subject = $purpose === 'admin'
            ? 'LINUXKAFE — redefinicao da senha de administrador'
            : 'LINUXKAFE — redefinicao da senha';

        $body = sprintf(
            "Ola,\n\nFoi pedido um pedido de redefinicao de senha%s.\n"
            . "Codigo: %s\n\nSe nao foi voce, ignore esta mensagem.\n",
            $account !== '' ? sprintf(' para a conta "%s"', $account) : '',
            $token
        );

        $message = sprintf(
            "From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n\r\n%s",
            $this->from,
            $recipient,
            $subject,
            $body
        );

        return $this->send($recipient, $message);
    }

    public function describe(): string
    {
        return sprintf('enviado por SMTP para %s:%s', $this->host, $this->port);
    }

    /**
     * There is no queue here: the mail was sent directly.
     */
    public function forget(string $token, string $purpose): bool
    {
        return true;
    }

    /**
     * The command that sends the message.
     *
     * The `--user` argument appears **only** when there are
     * credentials: a local Postfix does not want them, and a relay that
     * does want them refuses an empty one.
     *
     * @return list<string>
     */
    public function buildCommand(string $recipient, string $message): array
    {
        $command = [
            'curl',
            '-sS',
            '--max-time',
            '20',
            '--url',
            sprintf('smtp://%s:%s', $this->host, $this->port),
        ];

        if ($this->user !== '' || $this->password !== '') {
            $command[] = '--user';
            $command[] = sprintf('%s:%s', $this->user, $this->password);
        }

        $command[] = '--mail-from';
        $command[] = $this->from;
        $command[] = '--mail-rcpt';
        $command[] = $recipient;
        $command[] = '--upload-file';
        $command[] = '-';

        return $command;
    }

    private function send(string $recipient, string $message): bool
    {
        $command = $this->buildCommand($recipient, $message);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return false;
        }

        fwrite($pipes[0], $message);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($process);

        // A non-zero exit means the message was **not** accepted. The
        // file delivery then takes over, and the token is not lost.
        return $status === 0;
    }
}

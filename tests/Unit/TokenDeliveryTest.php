<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\FileTokenDelivery;
use Xkhm\Service\SmtpTokenDelivery;
use Xkhm\Service\TokenDelivery;
use Xkhm\Service\TokenDispatcher;

/**
 * A mailer whose outcome the test decides.
 */
final class FakeTokenDelivery implements TokenDelivery
{
    public bool $called = false;

    public function __construct(private bool $succeeds)
    {
    }

    public function deliver(
        string $recipient,
        string $token,
        string $purpose,
        string $account = ''
    ): bool {
        $this->called = true;

        return $this->succeeds;
    }

    public function describe(): string
    {
        return $this->succeeds ? 'enviado' : 'correio indisponível';
    }
}

/**
 * The rules of the delivery.
 *
 * - the file is **always** written: it is the queue, and there is
 *   always a way in;
 * - when the application manages to deliver by e-mail, the token leaves
 *   the queue — exactly one consumer;
 * - when it does not, the token stays in the queue for the host's
 *   mailer;
 * - an SMTP server that does not want credentials is not given any.
 */
class TokenDeliveryTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xkhm-delivery-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    /**
     * A mailer that always fails.
     */
    private function failingMailer(): FakeTokenDelivery
    {
        return new FakeTokenDelivery(false);
    }

    /**
     * A mailer that always succeeds.
     */
    private function workingMailer(): FakeTokenDelivery
    {
        return new FakeTokenDelivery(true);
    }

    public function testTheFileIsWrittenEvenWhenTheMailFails(): void
    {
        $queue = new FileTokenDelivery($this->directory);
        $dispatcher = new TokenDispatcher($queue, $this->failingMailer());

        $dispatcher->deliver('alguem@example.com', str_repeat('a', 64), 'cliente');

        $files = glob($this->directory . '/*.txt') ?: [];

        $this->assertCount(1, $files, 'the queue must always hold the token');
    }

    public function testTheTokenStaysInTheQueueWhenTheMailFails(): void
    {
        $queue = new FileTokenDelivery($this->directory);
        $mailer = $this->failingMailer();
        $dispatcher = new TokenDispatcher($queue, $mailer);

        $dispatcher->deliver('alguem@example.com', str_repeat('a', 64), 'cliente');

        $this->assertTrue($mailer->called, 'the mailer must have been tried');
        $this->assertCount(1, glob($this->directory . '/*.txt') ?: []);
    }

    public function testTheTokenLeavesTheQueueWhenTheMailSucceeds(): void
    {
        $queue = new FileTokenDelivery($this->directory);
        $mailer = $this->workingMailer();
        $dispatcher = new TokenDispatcher($queue, $mailer);

        $dispatcher->deliver('alguem@example.com', str_repeat('a', 64), 'cliente');

        $this->assertTrue($mailer->called);
        $this->assertCount(
            0,
            glob($this->directory . '/*.txt') ?: [],
            'a delivered token is no longer pending'
        );
    }

    public function testWithoutAMailerTheTokenStaysInTheQueue(): void
    {
        $queue = new FileTokenDelivery($this->directory);
        $dispatcher = new TokenDispatcher($queue, null);

        $dispatcher->deliver('alguem@example.com', str_repeat('a', 64), 'cliente');

        $this->assertCount(
            1,
            glob($this->directory . '/*.txt') ?: [],
            'the host mailer will deliver it'
        );
    }

    public function testThePendingFileRecordsTheAccount(): void
    {
        $queue = new FileTokenDelivery($this->directory);

        $queue->deliver('mail@linuxkafe.com', str_repeat('b', 64), 'admin', 'linuxkafe');

        $files = glob($this->directory . '/*.txt') ?: [];
        $this->assertCount(1, $files);

        $contents = (string)file_get_contents($files[0]);

        $this->assertStringContainsString('recipient: mail@linuxkafe.com', $contents);
        $this->assertStringContainsString('account: linuxkafe', $contents);
        $this->assertStringContainsString('purpose: admin', $contents);
    }

    public function testTheSmtpCommandHasNoUserWithoutCredentials(): void
    {
        $delivery = new SmtpTokenDelivery('127.0.0.1', '25', '', '', 'safedata@linuxkafe.com');

        $command = $delivery->buildCommand('alguem@example.com', 'corpo');

        $this->assertNotContains(
            '--user',
            $command,
            'a server that does not want credentials must not be given any'
        );
        $this->assertContains('smtp://127.0.0.1:25', $command);
    }

    public function testTheSmtpCommandHasTheUserWithCredentials(): void
    {
        $delivery = new SmtpTokenDelivery('smtp.exemplo.pt', '587', 'utilizador', 'senha', 'a@b.pt');

        $command = $delivery->buildCommand('alguem@example.com', 'corpo');

        $this->assertContains('--user', $command);
        $this->assertContains('utilizador:senha', $command);
    }

    public function testTheDescriptionNeverContainsTheToken(): void
    {
        $queue = new FileTokenDelivery($this->directory);
        $dispatcher = new TokenDispatcher($queue, $this->workingMailer());

        $description = $dispatcher->describe();

        $this->assertStringNotContainsString(str_repeat('a', 64), $description);
        $this->assertNotSame('', $description);
    }
}

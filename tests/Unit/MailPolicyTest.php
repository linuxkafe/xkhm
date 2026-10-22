<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\MailPolicy;

/**
 * Who may receive the payment notices.
 *
 * The installation is not validated: a payment notice says, on its
 * face, that it is **not** a fiscal document. Sending it to a real
 * client would put that document in the inbox of somebody who paid.
 */
class MailPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS');
    }

    public function testTheDefaultIsTheOperator(): void
    {
        $this->assertSame(['mail@linuxkafe.com'], MailPolicy::allowed());
    }

    public function testAClientIsNotAllowed(): void
    {
        $this->assertFalse(MailPolicy::allows('xkhm-test@example.com'));
        $this->assertFalse(MailPolicy::allows('alguem@linuxkafe.com'));
    }

    public function testTheNoticeGoesToTheOperator(): void
    {
        $this->assertSame(
            'mail@linuxkafe.com',
            MailPolicy::recipientFor('xkhm-test@example.com')
        );
    }

    public function testTheAllowedAddressIsKept(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS=alguem@linuxkafe.com');

        $this->assertTrue(MailPolicy::allows('alguem@linuxkafe.com'));
        $this->assertSame(
            'alguem@linuxkafe.com',
            MailPolicy::recipientFor('alguem@linuxkafe.com')
        );
    }

    public function testTheListAcceptsSeveralAddresses(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS=a@linuxkafe.com, b@linuxkafe.com');

        $this->assertSame(['a@linuxkafe.com', 'b@linuxkafe.com'], MailPolicy::allowed());
    }

    public function testAnEmptyListFallsBackToTheOperator(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS=');

        $this->assertSame(['mail@linuxkafe.com'], MailPolicy::allowed());
    }

    public function testAMalformedAddressIsIgnored(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS=isto-não-é-um-email, bom@linuxkafe.com');

        $this->assertSame(['bom@linuxkafe.com'], MailPolicy::allowed());
    }

    public function testTheComparisonIsCaseInsensitive(): void
    {
        putenv('XKHM_MAILER_NOTICE_RECIPIENTS=Alguem@LinuxKafe.com');

        $this->assertTrue(MailPolicy::allows('alguem@linuxkafe.com'));
    }
}

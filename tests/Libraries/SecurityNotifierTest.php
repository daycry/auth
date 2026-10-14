<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Libraries;

use CodeIgniter\Events\Events;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Libraries\SecurityNotifier;
use Daycry\Auth\Models\UserModel;
use Tests\Support\CapturingEmail;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SecurityNotifierTest extends DatabaseTestCase
{
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        CapturingEmail::install();

        $this->member        = fake(UserModel::class, ['username' => 'notified']);
        $this->member->email = 'notified@example.com';
        model(UserModel::class)->save($this->member);
    }

    public function testEachNoticeIsSentToTheExpectedAddressWithItsSubject(): void
    {
        $notifier = new SecurityNotifier();

        $this->assertTrue($notifier->passwordChanged($this->member));
        $this->assertTrue($notifier->emailChangeRequested($this->member, 'new@example.com'));
        $this->assertTrue($notifier->emailChanged($this->member, 'old@example.com'));
        $this->assertTrue($notifier->accountDeleted($this->member));
        $this->assertTrue($notifier->totpDisabled($this->member));

        $subjects = array_column(CapturingEmail::$sent, 'subject', 'to');
        $this->assertCount(5, CapturingEmail::$sent);
        $this->assertSame(lang('Auth.securityEmailChangedSubject'), $subjects['old@example.com']);

        $toMember = array_column(CapturingEmail::sentTo('notified@example.com'), 'subject');
        $this->assertSame([
            lang('Auth.securityPasswordChangedSubject'),
            lang('Auth.securityEmailChangeRequestedSubject'),
            lang('Auth.securityAccountDeletedSubject'),
            lang('Auth.securityTotpDisabledSubject'),
        ], $toMember);

        // Rendered from the translated lines, never raw lang keys.
        foreach (CapturingEmail::$sent as $message) {
            $this->assertStringNotContainsString('Auth.', $message['body']);
        }
        $this->assertStringContainsString('new@example.com', CapturingEmail::sentTo('notified@example.com')[1]['body']);
    }

    public function testNothingIsSentWhenDisabled(): void
    {
        $this->injectMockAttributesSecurity(['securityNotificationEmails' => false]);

        $this->assertFalse((new SecurityNotifier())->passwordChanged($this->member));
        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testAccountWithoutEmailIsSkipped(): void
    {
        // OAuth-created accounts have no e-mail address.
        $oauthOnly = fake(UserModel::class, ['username' => 'oauth-only']);

        $this->assertFalse((new SecurityNotifier())->passwordChanged($oauthOnly));
        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testTransportFailureAndExceptionAreSwallowed(): void
    {
        CapturingEmail::$failNext = true;
        $this->assertFalse((new SecurityNotifier())->passwordChanged($this->member));

        CapturingEmail::$throwNext = true;
        $this->assertFalse((new SecurityNotifier())->passwordChanged($this->member));

        $this->assertSame([], CapturingEmail::$sent);
    }

    public function testSuspiciousLoginNeedsItsOwnFlagToo(): void
    {
        $notifier = new SecurityNotifier();

        $this->assertFalse($notifier->suspiciousLogin($this->member, ['new_ip'], '203.0.113.9', 'UA'));

        $this->injectMockAttributesSecurity(['suspiciousLoginAlerts' => true]);
        $this->assertTrue($notifier->suspiciousLogin($this->member, ['new_ip'], '203.0.113.9', 'UA'));

        $body = CapturingEmail::$sent[0]['body'];
        $this->assertStringContainsString('203.0.113.9', $body);
        $this->assertStringContainsString('new_ip', $body);
        $this->assertStringNotContainsString('Auth.', $body);
    }

    public function testSuspiciousLoginEventListenerIsWired(): void
    {
        // Proves src/Config/Events.php is discovered (a Registrar could not
        // register listeners).
        $this->injectMockAttributesSecurity(['suspiciousLoginAlerts' => true]);

        Events::trigger('suspicious-login', $this->member, ['new_device'], '198.51.100.7', 'TestAgent');

        $this->assertCount(1, CapturingEmail::sentTo('notified@example.com'));
        $this->assertSame(lang('Auth.securitySuspiciousLoginSubject'), CapturingEmail::$sent[0]['subject']);
    }
}

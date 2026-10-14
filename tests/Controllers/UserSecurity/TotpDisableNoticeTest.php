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

namespace Tests\Controllers\UserSecurity;

use Daycry\Auth\Libraries\TOTP;
use Tests\Support\CapturingEmail;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class TotpDisableNoticeTest extends SelfServiceTestCase
{
    public function testDisablingTwoFactorNotifiesTheUser(): void
    {
        $user = $this->userWithPassword('twofa@example.com');
        $user->enableTotp();
        $user->confirmTotp();

        $this->signedInAs($user)->post('account/security/totp/disable', [
            'token' => TOTP::getCode((string) $user->getTotpSecret()),
        ])->assertRedirect();

        $this->assertFalse($user->hasTotpEnabled());
        $this->assertSame([lang('Auth.securityTotpDisabledSubject')], array_column(CapturingEmail::sentTo('twofa@example.com'), 'subject'));
    }

    public function testAWrongCodeDisablesNothingAndSendsNothing(): void
    {
        $user = $this->userWithPassword('twofa@example.com');
        $user->enableTotp();
        $user->confirmTotp();

        $this->signedInAs($user)->post('account/security/totp/disable', ['token' => '000000'])->assertRedirect();

        $this->assertTrue($user->hasTotpEnabled());
        $this->assertSame([], CapturingEmail::$sent);
    }
}

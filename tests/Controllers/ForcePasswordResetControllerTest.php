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

namespace Tests\Controllers;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserModel;
use Tests\Support\SelfServiceTestCase;

/**
 * Covers the forced password-reset form: errors return to the form (not to
 * the `force_reset` redirect, which defaults to '/') and the full password
 * policy applies, as on registration.
 *
 * @internal
 */
final class ForcePasswordResetControllerTest extends SelfServiceTestCase
{
    public function testWrongCurrentPasswordReturnsToTheForm(): void
    {
        $user = $this->userWithPassword();
        $user->forcePasswordReset();

        $result = $this->signedInAs($user)->post('auth/force-reset', [
            'current_password'     => 'not-the-password',
            'new_password'         => 'Brand-New-Pass-123',
            'new_password_confirm' => 'Brand-New-Pass-123',
        ]);

        $result->assertRedirectTo(site_url('auth/force-reset'));
        $result->assertSessionHas('error', lang('Auth.invalidCurrentPassword'));
    }

    public function testValidationErrorsReturnToTheForm(): void
    {
        $user = $this->userWithPassword();
        $user->forcePasswordReset();

        $result = $this->signedInAs($user)->post('auth/force-reset', [
            'current_password'     => self::PASSWORD,
            'new_password'         => 'Brand-New-Pass-123',
            'new_password_confirm' => 'something-else',
        ]);

        $result->assertRedirectTo(site_url('auth/force-reset'));
        $result->assertSessionHas('errors');
    }

    public function testNewPasswordMustSatisfyThePolicy(): void
    {
        $this->injectMockAttributesSecurity(['minimumPasswordLength' => 30]);

        $user = $this->userWithPassword();
        $user->forcePasswordReset();

        $result = $this->signedInAs($user)->post('auth/force-reset', [
            'current_password'     => self::PASSWORD,
            'new_password'         => 'Brand-New-Pass-123',
            'new_password_confirm' => 'Brand-New-Pass-123',
        ]);

        $result->assertRedirectTo(site_url('auth/force-reset'));
        $result->assertSessionHas('error', lang('Auth.errorPasswordLength', [30]));

        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertTrue($fresh->requiresPasswordReset());
        $this->assertTrue(service('passwords')->verify(self::PASSWORD, $fresh->password_hash));
    }

    public function testValidNewPasswordClearsTheFlag(): void
    {
        $user = $this->userWithPassword();
        $user->forcePasswordReset();

        $result = $this->signedInAs($user)->post('auth/force-reset', [
            'current_password'     => self::PASSWORD,
            'new_password'         => 'Brand-New-Pass-123',
            'new_password_confirm' => 'Brand-New-Pass-123',
        ]);

        $result->assertRedirectTo(config('Auth')->loginRedirect());

        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertFalse($fresh->requiresPasswordReset());
        $this->assertTrue(service('passwords')->verify('Brand-New-Pass-123', $fresh->password_hash));
    }
}

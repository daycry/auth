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

use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class SecurityOverviewTest extends SelfServiceTestCase
{
    public function testOverviewLinksToTheRegisteredSelfServiceScreens(): void
    {
        $result = $this->signedInAs($this->userWithPassword())->get('account/security');

        $result->assertStatus(200);
        $result->assertSee(lang('Auth.changePasswordTitle'));
        $result->assertSee(lang('Auth.changeEmailTitle'));
        $result->assertSee(lang('Auth.deleteAccountTitle'));
        $result->assertSee(url_to('security-delete-account'));
    }
}

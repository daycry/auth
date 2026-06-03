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

use Tests\Support\TestCase;

/**
 * @internal
 */
final class WebAuthnViewsTest extends TestCase
{
    public function testSetupViewRendersCeremonyEndpoints(): void
    {
        $html = view(setting('Auth.views')['webauthn_setup'], ['credentials' => []]);

        $this->assertStringContainsString('webauthn/register/options', $html);
        $this->assertStringContainsString('navigator.credentials', $html);
    }

    public function testTwoFactorViewRendersAssertionFetch(): void
    {
        $html = view(setting('Auth.views')['webauthn_2fa_verify']);

        $this->assertStringContainsString('webauthn/2fa/options', $html);
        $this->assertStringContainsString('auth/a/verify', $html);
    }
}

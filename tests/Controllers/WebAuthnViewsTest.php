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

    public function testCeremonyJsEmitsCsrfTokenAndHeader(): void
    {
        $html = view('\Daycry\Auth\Views\_webauthn_js');

        // The ceremony JS must carry CI4's CSRF header on every POST and rotate
        // the cached hash from the JSON response, otherwise the feature is
        // non-functional under the default cookie CSRF protection.
        $this->assertStringContainsString('const CSRF_HEADER', $html);
        $this->assertStringContainsString('CSRF_HASH', $html);
        $this->assertStringContainsString(csrf_header(), $html);
        $this->assertStringContainsString('[CSRF_HEADER]: CSRF_HASH', $html);
    }

    public function testTwoFactorViewRendersAssertionFetch(): void
    {
        $html = view(setting('Auth.views')['webauthn_2fa_verify']);

        $this->assertStringContainsString('webauthn/2fa/options', $html);
        $this->assertStringContainsString('auth/a/verify', $html);
    }
}

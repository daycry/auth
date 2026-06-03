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

namespace Tests\Support\WebAuthn;

/**
 * Silences web-auth/webauthn-lib's own internal E_USER_DEPRECATED notices for
 * the duration of a test.
 *
 * The library (v5.3) deprecates the still-required RP-entity `$name` parameter,
 * which the WebAuthn ceremonies build on every request. Under
 * CODEIGNITER_SCREAM_DEPRECATIONS=1 (set in phpunit.xml.dist) CodeIgniter
 * promotes every E_USER_DEPRECATED to a fatal ErrorException, so without this
 * guard any test that drives a ceremony would die. Only deprecations
 * originating inside the library are swallowed; deprecations from our own code
 * still fall through to CodeIgniter's handler.
 *
 * Usage: `use SuppressesWebauthnDeprecations;` then call
 * `$this->suppressWebauthnDeprecations()` at the end of setUp() and
 * `$this->restoreWebauthnDeprecations()` at the start of tearDown().
 */
trait SuppressesWebauthnDeprecations
{
    protected function suppressWebauthnDeprecations(): void
    {
        set_error_handler(
            static fn (int $severity, string $message, string $file = ''): bool => str_contains($file, 'web-auth' . DIRECTORY_SEPARATOR . 'webauthn-lib')
                || str_contains($message, 'web-auth/webauthn-lib'),
            E_USER_DEPRECATED,
        );
    }

    protected function restoreWebauthnDeprecations(): void
    {
        restore_error_handler();
    }
}

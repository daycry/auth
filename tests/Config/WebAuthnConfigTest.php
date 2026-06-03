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

namespace Tests\Config;

use Daycry\Auth\Config\AuthSecurity;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class WebAuthnConfigTest extends TestCase
{
    public function testWebAuthnDefaults(): void
    {
        $config = new AuthSecurity();

        $this->assertFalse($config->webauthnEnabled);
        $this->assertNull($config->webauthnRelyingPartyId);
        $this->assertSame('Daycry Auth', $config->webauthnRelyingPartyName);
        $this->assertSame([], $config->webauthnAllowedOrigins);
        $this->assertSame('preferred', $config->webauthnUserVerification);
        $this->assertSame('preferred', $config->webauthnResidentKey);
        $this->assertSame('none', $config->webauthnAttestationConveyance);
        $this->assertNull($config->webauthnAuthenticatorAttachment);
        $this->assertSame(60000, $config->webauthnTimeout);
        $this->assertSame(120, $config->webauthnChallengeTtl);
        $this->assertSame(10, $config->webauthnMaxCredentialsPerUser);
    }
}

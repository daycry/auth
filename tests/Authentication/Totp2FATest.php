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

namespace Tests\Authentication;

use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Libraries\TOTP;
use Daycry\Auth\Models\UserIdentityModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class Totp2FATest extends DatabaseTestCase
{
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeUser();
    }

    // -----------------------------------------------------------------------
    // TOTP Library tests
    // -----------------------------------------------------------------------

    public function testGenerateSecretReturnsBase32String(): void
    {
        $secret = TOTP::generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThan(10, strlen($secret));
    }

    public function testGenerateSecretIsUnique(): void
    {
        $a = TOTP::generateSecret();
        $b = TOTP::generateSecret();

        $this->assertNotSame($a, $b);
    }

    public function testGetCodeReturnsSixDigitString(): void
    {
        $secret = TOTP::generateSecret();
        $code   = TOTP::getCode($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testVerifyCurrentCode(): void
    {
        $secret = TOTP::generateSecret();
        $code   = TOTP::getCode($secret);

        $this->assertTrue(TOTP::verify($secret, $code));
    }

    public function testVerifyPreviousTimeStep(): void
    {
        $secret    = TOTP::generateSecret();
        $timestamp = time() - 30; // 1 step back
        $code      = TOTP::getCode($secret, $timestamp);

        // Should be accepted within window=1
        $this->assertTrue(TOTP::verify($secret, $code, 1));
    }

    public function testVerifyRejectsExpiredCode(): void
    {
        $secret    = TOTP::generateSecret();
        $timestamp = time() - 120; // 4 steps back, outside default window
        $code      = TOTP::getCode($secret, $timestamp);

        $this->assertFalse(TOTP::verify($secret, $code, 1));
    }

    public function testVerifyRejectsWrongCode(): void
    {
        $secret = TOTP::generateSecret();

        $this->assertFalse(TOTP::verify($secret, '000000', 0));
    }

    public function testVerifyIsCaseSensitiveOnSecret(): void
    {
        $secret = TOTP::generateSecret();
        $code   = TOTP::getCode($secret);

        // TOTP library normalises to uppercase internally, so lowercase also works
        $this->assertTrue(TOTP::verify(strtolower($secret), $code));
    }

    public function testBase32EncodeDecode(): void
    {
        $original = random_bytes(20);
        $encoded  = TOTP::base32Encode($original);
        $decoded  = TOTP::base32Decode($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testGetOtpAuthUrlFormat(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $url    = TOTP::getOtpAuthUrl($secret, 'user@example.com', 'MyApp');

        $this->assertStringStartsWith('otpauth://totp/', $url);
        $this->assertStringContainsString('secret=' . $secret, $url);
        $this->assertStringContainsString('issuer=MyApp', $url);
        $this->assertStringContainsString('digits=6', $url);
        $this->assertStringContainsString('period=30', $url);
    }

    // -----------------------------------------------------------------------
    // HasTotp trait tests (via User entity)
    // -----------------------------------------------------------------------

    public function testHasTotpEnabledFalseByDefault(): void
    {
        $this->assertFalse($this->user->hasTotpEnabled());
    }

    public function testEnableTotpStoresSecret(): void
    {
        $otpAuthUrl = $this->user->enableTotp('TestApp');

        $this->assertStringStartsWith('otpauth://totp/', $otpAuthUrl);
        $this->assertTrue($this->user->hasTotpEnabled());
        $this->assertNotEmpty($this->user->getTotpSecret());
    }

    public function testEnableTotpReplacesExistingSecret(): void
    {
        $this->user->enableTotp('TestApp');
        $firstSecret = $this->user->getTotpSecret();

        $this->user->enableTotp('TestApp');
        $secondSecret = $this->user->getTotpSecret();

        // Secrets should be different (new one generated)
        $this->assertNotSame($firstSecret, $secondSecret);

        // Only one TOTP_SECRET identity should exist
        /** @var UserIdentityModel $model */
        $model      = model(UserIdentityModel::class);
        $identities = $model->getIdentitiesByTypes($this->user, [IdentityType::TOTP_SECRET->value]);
        $this->assertCount(1, $identities);
    }

    public function testDisableTotpRemovesSecret(): void
    {
        $this->user->enableTotp('TestApp');
        $this->assertTrue($this->user->hasTotpEnabled());

        $this->user->disableTotp();
        $this->assertFalse($this->user->hasTotpEnabled());
        $this->assertNull($this->user->getTotpSecret());
    }

    public function testVerifyTotpCodeReturnsFalseWhenNotConfigured(): void
    {
        $this->assertFalse($this->user->verifyTotpCode('123456'));
    }

    public function testVerifyTotpCodeWithCorrectCode(): void
    {
        $this->user->enableTotp('TestApp');
        $secret = $this->user->getTotpSecret();

        $this->assertNotNull($secret);

        $code = TOTP::getCode($secret);
        $this->assertTrue($this->user->verifyTotpCode($code));
    }

    public function testVerifyTotpCodeWithWrongCode(): void
    {
        $this->user->enableTotp('TestApp');

        $this->assertFalse($this->user->verifyTotpCode('000000'));
    }

    public function testGetTotpIdentityReturnsNullWhenNotConfigured(): void
    {
        $this->assertNull($this->user->getTotpIdentity());
    }

    public function testGetTotpIdentityReturnsIdentityWhenEnabled(): void
    {
        $this->user->enableTotp('TestApp');

        $identity = $this->user->getTotpIdentity();
        $this->assertNotNull($identity);
        $this->assertSame(IdentityType::TOTP_SECRET->value, $identity->type);
    }
}

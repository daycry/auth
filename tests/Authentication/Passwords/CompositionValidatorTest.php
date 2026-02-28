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

namespace Tests\Authentication\Passwords;

use Daycry\Auth\Authentication\Passwords\CompositionValidator;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Exceptions\AuthenticationException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class CompositionValidatorTest extends TestCase
{
    private CompositionValidator $validator;
    private AuthConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config    = config(AuthConfig::class);
        $this->validator = new CompositionValidator($this->config);
    }

    public function testPasswordMeetsMinimumLength(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('abcdefgh');

        $this->assertTrue($result->isOK());
    }

    public function testPasswordExceedsMinimumLength(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('abcdefghijklmnop');

        $this->assertTrue($result->isOK());
    }

    public function testPasswordTooShort(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('abc');

        $this->assertFalse($result->isOK());
        $this->assertNotNull($result->reason());
        $this->assertNotNull($result->extraInfo());
    }

    public function testPasswordExactlyOneCharShort(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('abcdefg');

        $this->assertFalse($result->isOK());
    }

    public function testEmptyPasswordFails(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('');

        $this->assertFalse($result->isOK());
    }

    public function testZeroMinimumLengthThrowsException(): void
    {
        $this->config->minimumPasswordLength = 0;

        $this->expectException(AuthenticationException::class);

        $this->validator->check('password');
    }

    public function testUtf8PasswordLengthMeasured(): void
    {
        $this->config->minimumPasswordLength = 3;

        // 3 multibyte characters should pass
        $result = $this->validator->check("\u{00E9}\u{00E9}\u{00E9}");

        $this->assertTrue($result->isOK());
    }

    public function testUserParameterIsOptional(): void
    {
        $this->config->minimumPasswordLength = 8;

        $result = $this->validator->check('abcdefgh', null);

        $this->assertTrue($result->isOK());
    }
}

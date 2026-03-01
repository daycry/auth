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

use Daycry\Auth\Authentication\Passwords\DictionaryValidator;
use Daycry\Auth\Config\AuthSecurity as AuthConfig;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DictionaryValidatorTest extends TestCase
{
    private DictionaryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $config          = config(AuthConfig::class);
        $this->validator = new DictionaryValidator($config);
    }

    public function testCommonPasswordFails(): void
    {
        // "password" is one of the most common passwords
        $result = $this->validator->check('password');

        $this->assertFalse($result->isOK());
        $this->assertNotNull($result->reason());
        $this->assertNotNull($result->extraInfo());
    }

    public function testAnotherCommonPasswordFails(): void
    {
        $result = $this->validator->check('123456');

        $this->assertFalse($result->isOK());
    }

    public function testUniquePasswordPasses(): void
    {
        $result = $this->validator->check('Xk9#mQ2$vL7!nR4@');

        $this->assertTrue($result->isOK());
    }

    public function testUserParameterIsOptional(): void
    {
        $result = $this->validator->check('Xk9#mQ2$vL7!nR4@', null);

        $this->assertTrue($result->isOK());
    }
}

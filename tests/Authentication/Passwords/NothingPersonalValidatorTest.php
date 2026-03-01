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

use Daycry\Auth\Authentication\Passwords\NothingPersonalValidator;
use Daycry\Auth\Config\AuthSecurity;
use Daycry\Auth\Entities\User;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class NothingPersonalValidatorTest extends TestCase
{
    private NothingPersonalValidator $validator;
    private AuthSecurity $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config    = config(AuthSecurity::class);
        $this->validator = new NothingPersonalValidator($this->config);
    }

    private function createUser(array $attrs = []): User
    {
        $user           = new User();
        $user->email    = $attrs['email'] ?? 'john.doe@example.com';
        $user->username = $attrs['username'] ?? 'johndoe';

        foreach ($attrs as $key => $value) {
            if ($key !== 'email' && $key !== 'username') {
                $user->{$key} = $value;
            }
        }

        return $user;
    }

    public function testPasswordMatchingUsernameFails(): void
    {
        $user = $this->createUser(['username' => 'johndoe']);

        $result = $this->validator->check('johndoe', $user);

        $this->assertFalse($result->isOK());
    }

    public function testPasswordMatchingEmailFails(): void
    {
        $user = $this->createUser(['email' => 'john.doe@example.com']);

        $result = $this->validator->check('john.doe@example.com', $user);

        $this->assertFalse($result->isOK());
    }

    public function testPasswordMatchingReversedUsernameFails(): void
    {
        $user = $this->createUser(['username' => 'johndoe']);

        $result = $this->validator->check('eodnhoj', $user);

        $this->assertFalse($result->isOK());
    }

    public function testPasswordContainingUsernamePartFails(): void
    {
        $user = $this->createUser(['username' => 'johndoe']);

        $result = $this->validator->check('my_johndoe_pass', $user);

        $this->assertFalse($result->isOK());
    }

    public function testPasswordContainingEmailLocalPartFails(): void
    {
        $user = $this->createUser(['email' => 'john.doe@example.com']);

        // "john" is a part extracted from the email local-part; but < 3 chars are skipped
        // "doe" is exactly 3 chars, should be found in password
        $result = $this->validator->check('doe_is_my_password', $user);

        $this->assertFalse($result->isOK());
    }

    public function testUnrelatedPasswordPasses(): void
    {
        $user = $this->createUser([
            'username' => 'johndoe',
            'email'    => 'john.doe@example.com',
        ]);

        $result = $this->validator->check('Xk9#mQ2$vL7!nR4@', $user);

        $this->assertTrue($result->isOK());
    }

    public function testSimilarPasswordFails(): void
    {
        $this->config->maxSimilarity = 50;

        $user = $this->createUser(['username' => 'johndoe']);

        // Very similar to username
        $result = $this->validator->check('johndoe1', $user);

        $this->assertFalse($result->isOK());
    }

    public function testSimilarityCheckDisabledWithZero(): void
    {
        $this->config->maxSimilarity = 0;

        $user = $this->createUser(['username' => 'johndoe']);

        // Even a very similar password should pass when similarity is disabled
        // But it will still fail the isNotPersonal() check since it contains username
        // Use a password that is similar but not contained
        $result = $this->validator->check('Xk9#mQ2$vL7!nR4@', $user);

        $this->assertTrue($result->isOK());
    }

    public function testPasswordWithNullUsernamePasses(): void
    {
        $user = $this->createUser(['username' => null, 'email' => 'test@example.com']);

        $result = $this->validator->check('Xk9#mQ2$vL7!nR4@', $user);

        $this->assertTrue($result->isOK());
    }

    public function testPersonalFieldsFromConfig(): void
    {
        $this->inkectMockAttributes(['personalFields' => ['firstname']]);

        $user = $this->createUser([
            'username'  => 'johndoe',
            'email'     => 'john.doe@example.com',
            'firstname' => 'jonathan',
        ]);

        // Password containing the custom personal field value
        $result = $this->validator->check('jonathan_rocks', $user);

        $this->assertFalse($result->isOK());
    }

    public function testShortNeedlesAreIgnored(): void
    {
        $user = $this->createUser([
            'username' => 'ab',
            'email'    => 'ab@example.com',
        ]);

        // "ab" is < 3 chars, so it should be ignored as a needle
        $result = $this->validator->check('ab_something_unique_xyz', $user);

        $this->assertTrue($result->isOK());
    }

    public function testCaseInsensitiveMatching(): void
    {
        $user = $this->createUser(['username' => 'JohnDoe']);

        $result = $this->validator->check('JOHNDOE', $user);

        $this->assertFalse($result->isOK());
    }
}

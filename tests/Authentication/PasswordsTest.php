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

use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Config\AuthSecurity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\AuthenticationException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PasswordsTest extends TestCase
{
    private function makePasswords(?AuthSecurity $config = null): Passwords
    {
        $config ??= new AuthSecurity();

        return new Passwords($config);
    }

    public function testHashProducesVerifiableHash(): void
    {
        $p    = $this->makePasswords();
        $hash = $p->hash('s3cret-password');

        $this->assertIsString($hash);
        $this->assertTrue($p->verify('s3cret-password', $hash));
        $this->assertFalse($p->verify('wrong-password', $hash));
    }

    public function testHashAlgorithmsArgon2(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available');
        }

        $config                 = new AuthSecurity();
        $config->hashAlgorithm  = PASSWORD_ARGON2ID;
        $config->hashMemoryCost = 65536;
        $config->hashTimeCost   = 4;
        $config->hashThreads    = 1;

        $p    = $this->makePasswords($config);
        $hash = $p->hash('argon-password');

        $this->assertIsString($hash);
        $this->assertTrue($p->verify('argon-password', $hash));
        $this->assertFalse($p->needsRehash($hash));
    }

    public function testNeedsRehashTrueWhenHashAlgorithmChanges(): void
    {
        $config                = new AuthSecurity();
        $config->hashAlgorithm = PASSWORD_BCRYPT;
        $config->hashCost      = 10;

        $hash = (new Passwords($config))->hash('foo');

        // Stronger config should mark the existing hash as needing rehash.
        $stronger           = new AuthSecurity();
        $stronger->hashCost = 12;
        $this->assertTrue($this->makePasswords($stronger)->needsRehash($hash));
    }

    public function testNeedsRehashFalseForFreshlyHashedPassword(): void
    {
        $p    = $this->makePasswords();
        $hash = $p->hash('foo');

        $this->assertFalse($p->needsRehash($hash));
    }

    public function testCheckThrowsWhenNoUserProvided(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->makePasswords()->check('password');
    }

    public function testCheckRejectsEmptyPassword(): void
    {
        $result = $this->makePasswords()->check('', new User());

        $this->assertFalse($result->isOK());
    }

    public function testCheckTrimsAndRejectsWhitespaceOnlyPassword(): void
    {
        $result = $this->makePasswords()->check('   ', new User());

        $this->assertFalse($result->isOK());
    }

    public function testCheckSucceedsForStrongPasswordWithMinimalValidatorChain(): void
    {
        $config                     = new AuthSecurity();
        $config->passwordValidators = [];

        $result = $this->makePasswords($config)->check('Some-very-long-and-unique-password-1234!', new User());

        $this->assertTrue($result->isOK());
    }

    public function testGetMaxLengthRuleSwitchesByAlgorithm(): void
    {
        $rule = Passwords::getMaxLengthRule();
        $this->assertContains($rule, ['max_byte[72]', 'max_length[255]']);
    }
}

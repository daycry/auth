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

namespace Tests\Validation;

use Daycry\Auth\Validation\ValidationRules;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class ValidationRulesTest extends DatabaseTestCase
{
    private ValidationRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new ValidationRules();
    }

    public function testGetRegistrationRulesContainsRequiredFields(): void
    {
        $rules = $this->rules->getRegistrationRules();

        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('password_confirm', $rules);
    }

    public function testGetRegistrationRulesUsernameHasRequiredRule(): void
    {
        $rules = $this->rules->getRegistrationRules();

        $this->assertContains('required', $rules['username']['rules']);
    }

    public function testGetRegistrationRulesEmailHasValidEmailRule(): void
    {
        $rules = $this->rules->getRegistrationRules();

        $this->assertContains('valid_email', $rules['email']['rules']);
    }

    public function testGetRegistrationRulesPasswordHasStrongPasswordRule(): void
    {
        $rules = $this->rules->getRegistrationRules();

        // strong_password[] is appended to registration password rules
        $hasStrongPassword = false;

        foreach ($rules['password']['rules'] as $rule) {
            if (str_starts_with((string) $rule, 'strong_password')) {
                $hasStrongPassword = true;
                break;
            }
        }

        $this->assertTrue($hasStrongPassword, 'Registration rules should include strong_password');
    }

    public function testGetLoginRulesContainsEmailAndPassword(): void
    {
        $rules = $this->rules->getLoginRules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function testGetLoginRulesEmailHasRequiredRule(): void
    {
        $rules = $this->rules->getLoginRules();

        $this->assertContains('required', $rules['email']['rules']);
    }

    public function testGetPasswordRulesHasRequiredRule(): void
    {
        $rules = $this->rules->getPasswordRules();

        $this->assertArrayHasKey('rules', $rules);
        $this->assertContains('required', $rules['rules']);
    }

    public function testGetPasswordRulesHasLabel(): void
    {
        $rules = $this->rules->getPasswordRules();

        $this->assertArrayHasKey('label', $rules);
        $this->assertNotEmpty($rules['label']);
    }

    public function testGetPasswordConfirmRulesHasRequiredAndMatchesRules(): void
    {
        $rules = $this->rules->getPasswordConfirmRules();

        $this->assertArrayHasKey('rules', $rules);
        $this->assertStringContainsString('required', $rules['rules']);
        $this->assertStringContainsString('matches[password]', $rules['rules']);
    }

    public function testGetPasswordConfirmRulesHasLabel(): void
    {
        $rules = $this->rules->getPasswordConfirmRules();

        $this->assertArrayHasKey('label', $rules);
        $this->assertNotEmpty($rules['label']);
    }
}

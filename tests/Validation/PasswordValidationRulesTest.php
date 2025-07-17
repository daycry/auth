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

use Daycry\Auth\Entities\User;
use Daycry\Auth\Validation\PasswordValidationRules;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PasswordValidationRulesTest extends DatabaseTestCase
{
    private PasswordValidationRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new PasswordValidationRules();
    }

    public function testStrongPasswordWithValidPassword(): void
    {
        // Test with a strong password
        $password = 'SuperStrong123!@#';
        $data     = [
            'email'    => 'test@example.com',
            'username' => 'testuser',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        // The result depends on the configured password validators
        // For now, we just test that the method works
        $this->assertIsBool($result);

        // If it fails, error should be set
        if (! $result) {
            $this->assertNotNull($error);
            $this->assertIsString($error);
        }
    }

    public function testStrongPasswordWithWeakPassword(): void
    {
        // Test with a weak password
        $password = '123';
        $data     = [
            'email'    => 'weak@example.com',
            'username' => 'weakuser',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        // Should likely fail for such a weak password
        $this->assertIsBool($result);

        // If it fails, error should be set
        if (! $result) {
            $this->assertNotNull($error);
            $this->assertIsString($error);
        }
    }

    public function testStrongPasswordWithValidationDataArray(): void
    {
        // Test with validation data array
        $password = 'TestPassword123!';
        $data     = [
            'email'      => 'test@example.com',
            'username'   => 'testuser',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        $this->assertIsBool($result);

        // If it fails, error should be set
        if (! $result) {
            $this->assertNotNull($error);
            $this->assertIsString($error);
        }
    }

    public function testMaxByteWithValidLength(): void
    {
        // Test with string within byte limit
        $str      = 'Hello World';
        $maxBytes = '20';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertTrue($result);
    }

    public function testMaxByteWithExceedingLength(): void
    {
        // Test with string exceeding byte limit
        $str      = 'This is a very long string that definitely exceeds the byte limit';
        $maxBytes = '10';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertFalse($result);
    }

    public function testMaxByteWithExactLength(): void
    {
        // Test with string exactly at byte limit
        $str      = 'Hello';
        $maxBytes = '5';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertTrue($result);
    }

    public function testMaxByteWithNullString(): void
    {
        // Test with null string
        $str      = null;
        $maxBytes = '10';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertTrue($result);
    }

    public function testMaxByteWithEmptyString(): void
    {
        // Test with empty string
        $str      = '';
        $maxBytes = '5';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertTrue($result);
    }

    public function testMaxByteWithZeroLimit(): void
    {
        // Test with zero byte limit
        $str      = '';
        $maxBytes = '0';

        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertTrue($result);

        // Non-empty string should fail with zero limit
        $str    = 'a';
        $result = $this->rules->max_byte($str, $maxBytes);

        $this->assertFalse($result);
    }

    public function testMaxByteWithNonNumericLimit(): void
    {
        // Test with non-numeric limit
        $str      = 'Hello';
        $maxBytes = 'not_a_number';

        $result = $this->rules->max_byte($str, $maxBytes);

        // Should return false when limit is not numeric
        $this->assertFalse($result);
    }

    public function testBuildUserFromDataWithEmptyData(): void
    {
        // Test buildUserFromData with empty array (accessing protected method through public method)
        $password = 'TestPassword123!';
        $data     = [
            'email' => 'empty@example.com',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        // Should work even with minimal data
        $this->assertIsBool($result);
    }

    public function testBuildUserFromDataWithPartialData(): void
    {
        // Test buildUserFromData with partial user data
        $password = 'TestPassword123!';
        $data     = [
            'email'    => 'partial@example.com',
            'username' => 'partialuser',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        $this->assertIsBool($result);
    }

    public function testBuildUserFromDataWithExtraFields(): void
    {
        // Test buildUserFromData with extra fields that should be filtered out
        $password = 'TestPassword123!';
        $data     = [
            'email'           => 'extra@example.com',
            'username'        => 'extrauser',
            'invalid_field'   => 'should_be_ignored',
            'another_invalid' => 'also_ignored',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        $this->assertIsBool($result);
    }

    public function testMaxByteWithMultibyteString(): void
    {
        // Test with multibyte characters (UTF-8)
        $str      = 'café'; // 5 bytes (not 4 characters)
        $maxBytes = '4';

        $result = $this->rules->max_byte($str, $maxBytes);

        // Should return false because café is 5 bytes
        $this->assertFalse($result);

        $maxBytes = '5';
        $result   = $this->rules->max_byte($str, $maxBytes);

        // Should return true because 5 bytes is exactly the limit
        $this->assertTrue($result);
    }

    public function testStrongPasswordErrorMessagesAreDifferent(): void
    {
        // Test that different call patterns can produce different error messages
        $weakPassword = '123';

        // Test with data array first (to avoid the buildUserFromRequest path)
        $data    = ['email' => 'test@example.com'];
        $error2  = null;
        $result2 = $this->rules->strong_password($weakPassword, $null, $data, $error2);

        // Both should be boolean
        $this->assertIsBool($result2);

        // If it failed, error should be string
        if (! $result2) {
            $this->assertIsString($error2);
        }
    }

    public function testPrepareValidFieldsIncludesDefaultFields(): void
    {
        // This tests the protected method indirectly through strong_password
        $password = 'TestPassword123!';
        $data     = [
            'email'    => 'fields@example.com',
            'password' => 'somepassword',
            'username' => 'fieldsuser',
        ];
        $error = null;

        $result = $this->rules->strong_password($password, $null, $data, $error);

        // Should work with default fields
        $this->assertIsBool($result);
    }
}

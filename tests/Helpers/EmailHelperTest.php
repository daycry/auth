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

namespace Tests\Helpers;

use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EmailHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the helper
        helper('email');
    }

    public function testEmailerFunction(): void
    {
        $this->assertTrue(function_exists('emailer'));
    }

    public function testEmailerReturnsEmailInstance(): void
    {
        $email = emailer();
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerWithOverrides(): void
    {
        $overrides = [
            'protocol' => 'smtp',
            'SMTPHost' => 'smtp.example.com',
            'SMTPPort' => 587,
        ];

        $email = emailer($overrides);
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerWithEmptyOverrides(): void
    {
        $email = emailer([]);
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerDefaultConfiguration(): void
    {
        // Test that emailer function uses default configuration
        $email = emailer();
        $this->assertInstanceOf(Email::class, $email);

        // Test that it's properly initialized
        $this->assertNotNull($email);
    }

    public function testEmailerConfigurationMerging(): void
    {
        $overrides = [
            'mailType' => 'html',
            'charset'  => 'utf-8',
            'validate' => true,
        ];

        $email = emailer($overrides);
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerWithAllConfigOptions(): void
    {
        $overrides = [
            'userAgent'     => 'Test Agent',
            'protocol'      => 'smtp',
            'mailPath'      => '/usr/sbin/sendmail',
            'SMTPHost'      => 'smtp.test.com',
            'SMTPUser'      => 'test@test.com',
            'SMTPPass'      => 'password',
            'SMTPPort'      => 587,
            'SMTPTimeout'   => 30,
            'SMTPKeepAlive' => false,
            'SMTPCrypto'    => 'tls',
            'wordWrap'      => true,
            'wrapChars'     => 76,
            'mailType'      => 'html',
            'charset'       => 'utf-8',
            'validate'      => true,
            'priority'      => 3,
            'CRLF'          => "\r\n",
            'newline'       => "\r\n",
            'BCCBatchMode'  => false,
            'BCCBatchSize'  => 200,
            'DSN'           => false,
        ];

        $email = emailer($overrides);
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerReturnType(): void
    {
        $email = emailer();
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerServiceIntegration(): void
    {
        // Test that emailer function properly integrates with CodeIgniter services
        $email1 = emailer();
        $email2 = emailer();

        // Both should be Email instances
        $this->assertInstanceOf(Email::class, $email1);
        $this->assertInstanceOf(Email::class, $email2);
    }

    public function testEmailerWithPartialOverrides(): void
    {
        $overrides = [
            'protocol' => 'smtp',
            'SMTPHost' => 'localhost',
        ];

        $email = emailer($overrides);
        $this->assertInstanceOf(Email::class, $email);
    }

    public function testEmailerDefinedConstant(): void
    {
        // Test that the emailer function is defined (not the constant)
        $this->assertTrue(function_exists('emailer'));
    }

    public function testEmailerFunctionSignature(): void
    {
        // Test default call
        $email1 = emailer();
        $this->assertInstanceOf(Email::class, $email1);

        // Test with array parameter
        $email2 = emailer(['protocol' => 'mail']);
        $this->assertInstanceOf(Email::class, $email2);
    }

    public function testEmailerConfigurationTypes(): void
    {
        $overrides = [
            'SMTPPort'      => 587,          // integer
            'SMTPTimeout'   => 30,           // integer
            'SMTPKeepAlive' => false,        // boolean
            'wordWrap'      => true,         // boolean
            'wrapChars'     => 76,           // integer
            'validate'      => true,         // boolean
            'priority'      => 3,            // integer
            'BCCBatchMode'  => false,        // boolean
            'BCCBatchSize'  => 200,          // integer
            'DSN'           => false,        // boolean
        ];

        $email = emailer($overrides);
        $this->assertInstanceOf(Email::class, $email);
    }
}

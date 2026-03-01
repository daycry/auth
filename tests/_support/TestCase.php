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

namespace Tests\Support;

use CodeIgniter\Config\Factories;
use CodeIgniter\Settings\Config\Settings as SettingsConfig;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Encryption;
use Config\Security;
use Config\Services;
use Daycry\Auth\Config\Auth;
use Daycry\Auth\Config\AuthOAuth;
use Daycry\Auth\Config\AuthSecurity;

/**
 * @internal
 */
abstract class TestCase extends CIUnitTestCase
{
    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();

        // Use Array Settings Handler
        /** @var SettingsConfig $configSettings */
        $configSettings           = config('Settings');
        $configSettings->handlers = ['array'];
        $settings                 = new Settings($configSettings);
        Services::injectMock('settings', $settings);

        // Load helpers that should be autoloaded
        helper(['auth', 'setting']);

        // Ensure from email is available anywhere during Tests
        setting('Email.fromEmail', 'foo@example.com');
        setting('Email.fromName', 'John Smith');

        // Clear any actions
        /** @var Auth $config */
        $config          = config('Auth');
        $config->actions = ['login' => null, 'register' => null];
        Factories::injectMock('config', 'Auth', $config);

        // Set Config\Security::$csrfProtection to 'session'
        /** @var Security $config */
        $config                 = config('Security');
        $config->csrfProtection = 'session';
        Factories::injectMock('config', 'Security', $config);

        // Provide a fixed encryption key so service('encrypter') works in tests.
        // The 64-char hex string gives a 32-byte key (AES-256).
        /** @var Encryption $encConfig */
        $encConfig      = config('Encryption');
        $encConfig->key = hex2bin('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        Factories::injectMock('config', 'Encryption', $encConfig);
    }

    protected function inkectMockAttributes(array $attributes = []): void
    {
        $config = config(Auth::class);

        foreach ($attributes as $attribute => $value) {
            $config->{$attribute} = $value;
        }

        Factories::injectMock('config', 'Auth', $config);
    }

    protected function inkectMockAttributesSecurity(array $attributes = []): void
    {
        $config = config(AuthSecurity::class);

        foreach ($attributes as $attribute => $value) {
            $config->{$attribute} = $value;
        }

        Factories::injectMock('config', 'AuthSecurity', $config);
    }

    protected function inkectMockAttributesOAuth(array $attributes = []): void
    {
        $config = config(AuthOAuth::class);

        foreach ($attributes as $attribute => $value) {
            $config->{$attribute} = $value;
        }

        Factories::injectMock('config', 'AuthOAuth', $config);
    }
}

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

namespace Tests\Controllers;

use Config\Services;
use Daycry\Auth\Auth as AuthService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

/**
 * Flash messages can carry user input (e.g. validation errors echoing a
 * field), so every auth form must escape them like login/register do.
 *
 * @internal
 */
final class AuthViewsEscapingTest extends TestCase
{
    private const PAYLOAD = '<script>alert(1)</script>';

    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['error'], $_SESSION['errors']);

        parent::tearDown();
    }

    #[DataProvider('provideFormViews')]
    public function testErrorFlashIsEscaped(string $viewKey): void
    {
        $_SESSION['error'] = self::PAYLOAD;

        $html = view(setting('Auth.views')[$viewKey], ['token' => 'abc']);

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
        $this->assertStringContainsString(esc(self::PAYLOAD), $html);
    }

    #[DataProvider('provideFormViews')]
    public function testErrorsFlashIsEscaped(string $viewKey): void
    {
        $_SESSION['errors'] = ['field' => self::PAYLOAD];

        $html = view(setting('Auth.views')[$viewKey], ['token' => 'abc']);

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
        $this->assertStringContainsString(esc(self::PAYLOAD), $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFormViews(): iterable
    {
        yield 'force reset' => ['force-password-reset'];

        yield 'magic link' => ['magic-link-login'];

        yield 'reset request' => ['password-reset-request'];

        yield 'reset form' => ['password-reset-form'];
    }
}

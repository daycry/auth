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

namespace Tests\Libraries;

use CodeIgniter\Exceptions\RuntimeException;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Interfaces\EmailDeliveryInterface;
use Daycry\Auth\Libraries\SecurityNotifier;
use Daycry\Auth\Libraries\TokenEmailSender;
use Daycry\Auth\Models\UserModel;
use LogicException;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class EmailDeliveryTest extends DatabaseTestCase
{
    public function testTokenEmailUsesTheReplaceableDeliveryService(): void
    {
        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
        $delivery = new class () implements EmailDeliveryInterface {
            public array $messages = [];

            public function send(string $to, string $subject, string $html): bool
            {
                $this->messages[] = [$to, $subject, $html];

                return true;
            }
        };
        Services::injectMock('emailDelivery', $delivery);
        $user        = fake(UserModel::class);
        $user->email = 'delivery@example.com';
        model(UserModel::class)->save($user);

        $raw = (new TokenEmailSender())->sendTokenEmail($user, IdentityType::MAGIC_LINK->value, 600, 'Sign in', setting('Auth.views')['magic-link-email']);

        $this->assertCount(1, $delivery->messages);
        $this->assertSame(['delivery@example.com', 'Sign in'], array_slice($delivery->messages[0], 0, 2));
        $this->assertStringContainsString($raw, (string) $delivery->messages[0][2]);
    }

    public function testSecurityNoticeUsesTheSameReplaceableDeliveryService(): void
    {
        $delivery = new class () implements EmailDeliveryInterface {
            public array $messages = [];

            public function send(string $to, string $subject, string $html): bool
            {
                $this->messages[] = [$to, $subject, $html];

                return true;
            }
        };
        Services::injectMock('emailDelivery', $delivery);
        $user        = fake(UserModel::class);
        $user->email = 'notice@example.com';

        $this->assertTrue((new SecurityNotifier())->passwordChanged($user));
        $this->assertCount(1, $delivery->messages);
        $this->assertSame('notice@example.com', $delivery->messages[0][0]);
    }

    public function testCustomTransportExceptionsKeepTheTokenSenderFailureContract(): void
    {
        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
        $delivery = new class () implements EmailDeliveryInterface {
            public function send(string $to, string $subject, string $html): bool
            {
                throw new LogicException('transport offline');
            }
        };
        Services::injectMock('emailDelivery', $delivery);
        $user        = fake(UserModel::class);
        $user->email = 'failure@example.com';

        $this->expectException(RuntimeException::class);
        (new TokenEmailSender())->sendTokenEmail($user, IdentityType::MAGIC_LINK->value, 600, 'Sign in', setting('Auth.views')['magic-link-email']);
    }
}

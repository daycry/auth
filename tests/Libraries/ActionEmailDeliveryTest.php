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

use Config\Services;
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\EmailDeliveryInterface;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class ActionEmailDeliveryTest extends DatabaseTestCase
{
    public function testSecondFactorMailUsesTheReplaceableTransport(): void
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
        $user   = fake(UserModel::class);
        $action = new class () extends Email2FA {
            public function deliver(User $user): void
            {
                $this->sendActionEmail($user, 'member@example.com', 'Verification code', 'action_email_2fa_email', ['code' => '123456']);
            }
        };
        $action->deliver($user);
        $this->assertCount(1, $delivery->messages);
        $this->assertSame('member@example.com', $delivery->messages[0][0]);
        $this->assertSame('Verification code', $delivery->messages[0][1]);
        $this->assertStringContainsString('123456', (string) $delivery->messages[0][2]);
    }
}

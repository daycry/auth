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

use CodeIgniter\Email\Email;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Libraries\TokenEmailSender;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TokenEmailSenderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register the library routes so the email view's url_to() resolves.
        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        // Replace the email transport with one that never actually sends, so the
        // flow can be exercised without SMTP. All other Email behaviour (building
        // the message, etc.) is preserved.
        Services::injectMock('email', new class () extends Email {
            public function send($autoClear = true)
            {
                return true;
            }
        });
    }

    public function testStoresHashedTokenButReturnsRawToken(): void
    {
        $user        = fake(UserModel::class);
        $user->email = 'token-sender@example.com';
        model(UserModel::class)->save($user);

        $sender = new TokenEmailSender();
        $raw    = $sender->sendTokenEmail(
            $user,
            Session::ID_TYPE_MAGIC_LINK,
            3600,
            'Subject',
            setting('Auth.views')['magic-link-email'],
        );

        // The token returned (and emailed) is the raw value, not a 64-char hash.
        $this->assertNotSame('', $raw);
        $this->assertNotSame(hash('sha256', $raw), $raw);

        // The database stores ONLY the SHA-256 hash — never the raw token.
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => hash('sha256', $raw),
        ]);
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => $raw,
        ]);
    }
}

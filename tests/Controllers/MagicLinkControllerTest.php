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

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Covers the magic-link verify flow: the token is single-use, expiry is
 * enforced, and the round-trip works with the hashed-at-rest token storage.
 *
 * @internal
 */
final class MagicLinkControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        setting('AuthSecurity.allowMagicLinkLogins', true);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $user        = fake(UserModel::class);
        $user->email = 'magic@example.com';
        model(UserModel::class)->save($user);

        $this->db->table($this->tables['identities'])->truncate();
        model(UserIdentityModel::class)->createEmailIdentity($user, [
            'email'    => 'magic@example.com',
            'password' => 'some-password-123',
        ]);

        $this->userId = (int) $user->id;
    }

    /**
     * Inserts a magic-link identity exactly as TokenEmailSender does
     * (SHA-256-hashed secret) and returns the RAW token.
     */
    private function seedMagicToken(int $lifetimeSeconds): string
    {
        $raw = 'magic-token-' . bin2hex(random_bytes(8));

        model(UserIdentityModel::class)->insert([
            'user_id' => $this->userId,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => hash('sha256', $raw),
            'expires' => Time::now()->addSeconds($lifetimeSeconds)->format('Y-m-d H:i:s'),
        ]);

        return $raw;
    }

    public function testVerifyConsumesTokenOnSuccess(): void
    {
        $token = $this->seedMagicToken(3600);

        $result = $this->get('login/verify-magic-link?token=' . $token);
        $result->assertRedirect();

        // The magic-link token must be single-use (deleted after verification).
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->userId,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
        ]);

        // Re-using the same token must now fail to find an identity.
        $reuse = $this->get('login/verify-magic-link?token=' . $token);
        $reuse->assertRedirect();
        $reuse->assertSessionHas('error');
    }

    public function testVerifyWithExpiredTokenIsRejected(): void
    {
        $token = $this->seedMagicToken(-3600); // already expired

        $result = $this->get('login/verify-magic-link?token=' . $token);

        $result->assertRedirect();
        $result->assertSessionHas('error');

        // Expired token is consumed regardless.
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->userId,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
        ]);
    }

    public function testVerifyWithUnknownTokenIsRejected(): void
    {
        $result = $this->get('login/verify-magic-link?token=this-token-does-not-exist');

        $result->assertRedirect();
        $result->assertSessionHas('error');
    }
}

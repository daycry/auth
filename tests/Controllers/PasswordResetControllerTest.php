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
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Covers the password-reset token flow: a reset token is single-use, expiry is
 * enforced, and the round-trip works with the hashed-at-rest token storage.
 *
 * @internal
 */
final class PasswordResetControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $user        = fake(UserModel::class);
        $user->email = 'reset@example.com';
        model(UserModel::class)->save($user);

        $this->db->table($this->tables['identities'])->truncate();
        model(UserIdentityModel::class)->createEmailIdentity($user, [
            'email'    => 'reset@example.com',
            'password' => 'old-password-123',
        ]);

        $this->userId = (int) $user->id;
    }

    /**
     * Inserts a reset-token identity exactly as TokenEmailSender does
     * (SHA-256-hashed secret) and returns the RAW token.
     */
    private function seedResetToken(int $lifetimeSeconds): string
    {
        $raw = 'reset-token-' . bin2hex(random_bytes(8));

        model(UserIdentityModel::class)->insert([
            'user_id' => $this->userId,
            'type'    => IdentityType::RESET_PASSWORD->value,
            'secret'  => hash('sha256', $raw),
            'expires' => Time::now()->addSeconds($lifetimeSeconds)->format('Y-m-d H:i:s'),
        ]);

        return $raw;
    }

    public function testResetSucceedsThenTokenIsSingleUse(): void
    {
        $token = $this->seedResetToken(3600);

        // First use: succeeds and redirects to login.
        $first = $this->post('password-reset/verify', [
            'token'            => $token,
            'password'         => 'BrandNewPass123',
            'password_confirm' => 'BrandNewPass123',
        ]);
        $first->assertRedirect();

        // The reset token must be consumed (single-use).
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->userId,
            'type'    => IdentityType::RESET_PASSWORD->value,
        ]);

        // Re-using the same token must no longer reset the password.
        $second = $this->post('password-reset/verify', [
            'token'            => $token,
            'password'         => 'AnotherPass123',
            'password_confirm' => 'AnotherPass123',
        ]);
        $second->assertRedirect();
        $second->assertSessionHas('error');
    }

    public function testResetWithExpiredTokenIsRejected(): void
    {
        $token = $this->seedResetToken(-3600); // already expired

        $result = $this->post('password-reset/verify', [
            'token'            => $token,
            'password'         => 'BrandNewPass123',
            'password_confirm' => 'BrandNewPass123',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');

        // Expired token is purged.
        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $this->userId,
            'type'    => IdentityType::RESET_PASSWORD->value,
        ]);
    }

    public function testResetWithUnknownTokenIsRejected(): void
    {
        $this->seedResetToken(3600); // a valid token exists, but we submit a different one

        $result = $this->post('password-reset/verify', [
            'token'            => 'totally-unknown-token',
            'password'         => 'BrandNewPass123',
            'password_confirm' => 'BrandNewPass123',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');
    }
}

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

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Test\AuthenticationTesting;

/**
 * Base for the UserSecurityController self-service endpoint tests: registers
 * the documented route group and captures outgoing e-mail.
 *
 * @internal
 */
abstract class SelfServiceTestCase extends DatabaseTestCase
{
    use AuthenticationTesting;
    use FeatureTestTrait;

    protected const PASSWORD = 'Correct-Horse-Battery-9';

    protected function setUp(): void
    {
        parent::setUp();

        $routes = service('routes');
        auth()->routes($routes);

        $controller = '\Daycry\Auth\Controllers\UserSecurityController::';
        $routes->group('account/security', ['filter' => 'auth:session'], static function ($routes) use ($controller): void {
            $routes->get('/', $controller . 'index', ['as' => 'security']);
            $routes->get('password', $controller . 'changePasswordView', ['as' => 'security-change-password']);
            $routes->post('password', $controller . 'changePassword', ['as' => 'security-change-password-action']);
            $routes->get('email', $controller . 'changeEmailView', ['as' => 'security-change-email']);
            $routes->post('email', $controller . 'changeEmail', ['as' => 'security-change-email-action']);
            $routes->get('email/confirm', $controller . 'confirmEmailChangeView', ['as' => 'security-confirm-email']);
            $routes->post('email/confirm', $controller . 'confirmEmailChange', ['as' => 'security-confirm-email-action']);
            $routes->post('oauth/(:segment)/unlink', $controller . 'unlinkOauth/$1', ['as' => 'security-unlink-oauth']);
            $routes->get('delete', $controller . 'deleteAccountView', ['as' => 'security-delete-account']);
            $routes->post('delete', $controller . 'deleteAccount', ['as' => 'security-delete-account-action']);
            $routes->get('delete/confirm', $controller . 'confirmDeleteAccountView', ['as' => 'security-confirm-delete']);
            $routes->post('delete/confirm', $controller . 'confirmDeleteAccount', ['as' => 'security-confirm-delete-action']);
        });
        Services::injectMock('routes', $routes);

        CapturingEmail::install();
    }

    /**
     * A regular account: e-mail + password identity.
     */
    protected function userWithPassword(string $email = 'member@example.com'): User
    {
        $user = fake(UserModel::class, ['username' => strstr($email, '@', true)]);
        model(UserIdentityModel::class)->createEmailIdentity($user, ['email' => $email, 'password' => self::PASSWORD]);

        return model(UserModel::class)->findById($user->id);
    }

    /**
     * An OAuth-created account: no e-mail and no password, one social identity.
     */
    protected function oauthOnlyUser(string $provider = 'github'): User
    {
        $user = fake(UserModel::class, ['username' => 'social_' . bin2hex(random_bytes(3))]);
        model(UserIdentityModel::class)->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::oauthProvider($provider),
            'secret'  => 'social-' . bin2hex(random_bytes(4)),
            'name'    => 'Social User',
        ]);

        return model(UserModel::class)->findById($user->id);
    }

    /**
     * Signs the user in and carries the resulting session (including the
     * interactive sign-in stamp) into the following requests — each
     * FeatureTestTrait request otherwise starts from an empty $_SESSION.
     */
    protected function signedInAs(User $user): static
    {
        $this->actingAs($user);
        $this->withSession();

        return $this;
    }

    /**
     * Makes the carried session's interactive sign-in look old.
     */
    protected function ageSignIn(int $seconds): void
    {
        $_SESSION[Session::AUTHENTICATED_AT_KEY] = time() - $seconds;
        $this->withSession();
    }

    protected function flash(string $key): ?string
    {
        $value = session($key);

        return $value === null ? null : (string) $value;
    }
}

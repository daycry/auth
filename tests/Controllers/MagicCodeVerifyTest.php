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
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class MagicCodeVerifyTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.allowMagicLinkLogins', true);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    private function makeUser(string $email): User
    {
        $user        = fake(UserModel::class);
        $user->email = $email;
        model(UserModel::class)->save($user);

        return model(UserModel::class)->findById($user->id);
    }

    private function seedCode(int $userId, string $code, int $lifetime = 600): void
    {
        model(UserIdentityModel::class)->insert([
            'user_id' => $userId,
            'type'    => 'magic_code',
            'secret'  => hash('sha256', $code),
            'expires' => Time::now()->addSeconds($lifetime)->format('Y-m-d H:i:s'),
        ]);
    }

    public function testValidCodeLogsInThenIsSingleUse(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456');

        $first = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $first->assertRedirectTo(config('Auth')->loginRedirect());

        // Single-use: the identity is gone, a replay fails generically.
        $this->dontSeeInDatabase($this->tables['identities'], ['user_id' => $user->id, 'type' => 'magic_code']);
        $second = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $second->assertRedirectTo(route_to('magic-link-code'));
        $second->assertSessionHas('error');
    }

    public function testExpiredCodeIsRejected(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456', -60); // already expired

        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');
    }

    public function testWrongCodeIsRejected(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456');

        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '000000']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');
    }

    public function testCodeIsScopedToUser(): void
    {
        // A's code must NOT authenticate B's session. Verification is scoped to
        // the session-bound user's own MAGIC_CODE identity, never a global
        // secret lookup, so submitting A's code while logged into B's flow is
        // rejected (and A's identity is left intact).
        //
        // (The (type, secret) UNIQUE constraint on the identities table means
        // two users can never literally share a code; codes therefore differ.)
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $this->seedCode((int) $a->id, '111111');
        $this->seedCode((int) $b->id, '222222');

        // Submit A's code (111111) while the session belongs to B.
        $result = $this->withSession(['magicCodeEmail' => 'b@example.com'])
            ->post('login/magic-link/code', ['token' => '111111']);

        // A global lookup would have matched A's identity and logged someone in.
        // A scoped lookup rejects it: generic error, back to the code form.
        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');

        // Both codes untouched (A's never consumed cross-account; B's not deleted).
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $a->id, 'type' => 'magic_code']);
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $b->id, 'type' => 'magic_code']);
    }

    public function testValidCodeForSessionUserLogsInAndConsumesOnlyTheirOwn(): void
    {
        // Positive scoping: B's own code logs B in and consumes only B's identity,
        // leaving A's untouched.
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $this->seedCode((int) $a->id, '111111');
        $this->seedCode((int) $b->id, '222222');

        $result = $this->withSession(['magicCodeEmail' => 'b@example.com'])
            ->post('login/magic-link/code', ['token' => '222222']);
        $result->assertRedirectTo(config('Auth')->loginRedirect());

        // B's code consumed, A's untouched.
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $a->id, 'type' => 'magic_code']);
        $this->dontSeeInDatabase($this->tables['identities'], ['user_id' => $b->id, 'type' => 'magic_code']);
    }

    public function testNoPendingEmailRedirectsToMagicLink(): void
    {
        $result = $this->post('login/magic-link/code', ['token' => '123456']);
        $result->assertRedirectTo(route_to('magic-link'));
    }
}

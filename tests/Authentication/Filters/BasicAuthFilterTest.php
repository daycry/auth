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

namespace Tests\Authentication\Filters;

use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\BasicAuthFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class BasicAuthFilterTest extends FilterTestCase
{
    protected string $alias       = 'basic-auth';
    protected string $classname   = BasicAuthFilter::class;
    protected string $routeFilter = 'basic-auth';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];
    }

    public function testRejectsRequestWithoutHeader(): void
    {
        $result = $this->call('GET', 'protected-route');

        $result->assertStatus(401);
        $result->assertHeader('WWW-Authenticate');
    }

    public function testRejectsRequestWithMalformedHeader(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer not-basic'])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testRejectsRequestWithInvalidBase64(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Basic %%%%not-base64%%%%'])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testRejectsRequestWithMissingColon(): void
    {
        $payload = base64_encode('userwithoutcolon');

        $result = $this->withHeaders(['Authorization' => 'Basic ' . $payload])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testRejectsUnknownUser(): void
    {
        $payload = base64_encode('ghost@example.com:secret');

        $result = $this->withHeaders(['Authorization' => 'Basic ' . $payload])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testRejectsWrongPassword(): void
    {
        $email = 'alice@example.com';
        $this->createUserWithPassword('alice', $email, 'correct-password');

        $payload = base64_encode($email . ':wrong-password');

        $result = $this->withHeaders(['Authorization' => 'Basic ' . $payload])
            ->get('protected-route');

        $result->assertStatus(401);
    }

    public function testAcceptsValidEmailAndPassword(): void
    {
        $email    = 'alice@example.com';
        $password = 'secret_password_42';

        $this->createUserWithPassword('alice', $email, $password);

        $payload = base64_encode($email . ':' . $password);

        $result = $this->withHeaders(['Authorization' => 'Basic ' . $payload])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');
    }

    public function testAcceptsValidUsernameAndPassword(): void
    {
        $username = 'bob';
        $password = 'another_password_99';

        $this->createUserWithPassword($username, 'bob@example.com', $password);

        $payload = base64_encode($username . ':' . $password);

        $result = $this->withHeaders(['Authorization' => 'Basic ' . $payload])
            ->get('protected-route');

        $result->assertStatus(200);
    }

    /**
     * Creates and persists a User with the given email + password so that
     * the email_password identity row gets created via UserModel hooks.
     */
    private function createUserWithPassword(string $username, string $email, string $password): User
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);

        $user        = new User(['username' => $username, 'active' => true]);
        $user->email = $email;
        $user->setPassword($password);

        $userModel->save($user);

        return $userModel->findById($userModel->getInsertID());
    }

    public function testWwwAuthenticateUsesConfiguredRealm(): void
    {
        $this->injectMockAttributes(['basicAuthRealm' => 'My API']);

        $result = $this->call('GET', 'protected-route');

        $result->assertStatus(401);
        $this->assertStringContainsString(
            'realm="My API"',
            $result->response()->getHeaderLine('WWW-Authenticate'),
        );
    }
}

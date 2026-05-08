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

namespace Tests\Commands;

use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Commands\TokensCommand;
use Daycry\Auth\Entities\User as UserEntity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Test\MockInputOutput;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TokensCommandTest extends DatabaseTestCase
{
    private ?MockInputOutput $io = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        TokensCommand::resetInputOutput();
    }

    private function setMockIo(array $inputs = []): void
    {
        $this->io = new MockInputOutput();
        $this->io->setInputs($inputs);
        TokensCommand::setInputOutput($this->io);
    }

    private function createUser(string $email = 'user@example.com'): UserEntity
    {
        /** @var UserEntity $user */
        $user = fake(UserModel::class);
        model(UserIdentityModel::class)->createEmailIdentity($user, [
            'email'    => $email,
            'password' => 'secret123',
        ]);

        return $user;
    }

    public function testRequiresIdentifier(): void
    {
        $this->setMockIo();

        command('auth:tokens revoke');

        $this->assertStringContainsString('Specify -e <email>', $this->io->getOutputs());
    }

    public function testRejectsUnknownAction(): void
    {
        $this->setMockIo();

        command('auth:tokens unknown -e user@example.com');

        $this->assertStringContainsString('Unsupported action', $this->io->getOutputs());
    }

    public function testReportsUnknownUser(): void
    {
        $this->setMockIo();

        command('auth:tokens revoke -e ghost@example.com');

        $this->assertStringContainsString('User not found', $this->io->getOutputs());
    }

    public function testRevokesAccessTokensByDefault(): void
    {
        $user = $this->createUser('alice@example.com');

        // Issue two access tokens.
        $user->generateAccessToken('cli-1', ['*']);
        $user->generateAccessToken('cli-2', ['*']);

        $this->setMockIo();
        command('auth:tokens revoke -e alice@example.com');

        $this->assertStringContainsString('Revoked all access tokens', $this->io->getOutputs());

        // No active (non-revoked) access tokens should remain.
        $remainingActive = model(UserIdentityModel::class)
            ->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('revoked_at')
            ->countAllResults();

        $this->assertSame(0, $remainingActive);
    }

    public function testRevokesByUserId(): void
    {
        $user = $this->createUser('byid@example.com');
        $user->generateAccessToken('cli', ['*']);

        $this->setMockIo();
        command('auth:tokens revoke -i ' . (int) $user->id);

        $remainingActive = model(UserIdentityModel::class)
            ->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('revoked_at')
            ->countAllResults();

        $this->assertSame(0, $remainingActive);
    }

    public function testRevokesOnlyJwtRefreshTokensWhenRequested(): void
    {
        $user = $this->createUser('jwt@example.com');
        $user->generateAccessToken('keep-me', ['*']);

        // Insert a refresh token directly.
        $identityModel = model(UserIdentityModel::class);
        $identityModel->createJwtRefreshToken((int) $user->id, 'refresh-raw', '2030-01-01 00:00:00');

        $this->setMockIo();
        command('auth:tokens revoke -e jwt@example.com --type jwt_refresh');

        // Refresh tokens fully revoked.
        $unrevokedRefresh = model(UserIdentityModel::class)
            ->where('user_id', $user->id)
            ->where('type', IdentityType::JWT_REFRESH->value)
            ->where('revoked_at')
            ->countAllResults();
        $this->assertSame(0, $unrevokedRefresh);

        // Access tokens left untouched.
        $unrevokedAccess = model(UserIdentityModel::class)
            ->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('revoked_at')
            ->countAllResults();
        $this->assertSame(1, $unrevokedAccess);
    }

    public function testUnknownTypeFails(): void
    {
        $this->createUser('typed@example.com');

        $this->setMockIo();
        command('auth:tokens revoke -e typed@example.com --type unknown');

        $this->assertStringContainsString('Unknown --type', $this->io->getOutputs());
    }
}

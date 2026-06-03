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

namespace Tests\Models;

use CodeIgniter\Exceptions\LogicException;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\AccessToken;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * Targets methods on UserIdentityModel that aren't reached by other tests.
 *
 * @internal
 */
final class UserIdentityModelTest extends DatabaseTestCase
{
    private UserIdentityModel $identityModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityModel = model(UserIdentityModel::class);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = fake(UserModel::class);

        return $user;
    }

    public function testCheckUserIdRejectsIncompleteUser(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"$user->id" is null');

        $u = new User();
        $this->identityModel->getIdentities($u);
    }

    public function testGetIdentityBySecretReturnsNullForNullSecret(): void
    {
        $this->assertNotInstanceOf(
            UserIdentity::class,
            $this->identityModel->getIdentityBySecret(IdentityType::MAGIC_LINK->value, null),
        );
    }

    public function testGetIdentityBySecretFindsExistingRow(): void
    {
        $user = $this->makeUser();

        // Ephemeral tokens are stored as a SHA-256 hash, never the raw value.
        $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::MAGIC_LINK->value,
            'secret'  => hash('sha256', 'abc-123'),
        ]);

        $found = $this->identityModel->getIdentityBySecret(IdentityType::MAGIC_LINK->value, 'abc-123');
        $this->assertInstanceOf(UserIdentity::class, $found);
    }

    public function testGetIdentityBySecretMatchesHashedStoredSecretOnly(): void
    {
        $user = $this->makeUser();
        $raw  = 'raw-magic-token';

        $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::MAGIC_LINK->value,
            'secret'  => hash('sha256', $raw),
        ]);

        // Looking up by the RAW token finds the row (the model hashes the input).
        $this->assertInstanceOf(
            UserIdentity::class,
            $this->identityModel->getIdentityBySecret(IdentityType::MAGIC_LINK->value, $raw),
        );

        // Looking up by the already-hashed value must NOT match (no double hashing).
        $this->assertNotInstanceOf(
            UserIdentity::class,
            $this->identityModel->getIdentityBySecret(IdentityType::MAGIC_LINK->value, hash('sha256', $raw)),
        );
    }

    public function testGetIdentitiesByUserIdsReturnsRowsForMultipleUsers(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->identityModel->createEmailIdentity($a, ['email' => 'a@test.com', 'password' => 'pw1']);
        $this->identityModel->createEmailIdentity($b, ['email' => 'b@test.com', 'password' => 'pw2']);

        $rows = $this->identityModel->getIdentitiesByUserIds([(int) $a->id, (int) $b->id]);
        $this->assertCount(2, $rows);
    }

    public function testGetIdentitiesByTypesReturnsEmptyForEmptyTypeList(): void
    {
        $user = $this->makeUser();
        $this->assertSame([], $this->identityModel->getIdentitiesByTypes($user, []));
    }

    public function testGetIdentitiesByTypesFiltersToProvidedTypes(): void
    {
        $user = $this->makeUser();

        $this->identityModel->createEmailIdentity($user, ['email' => 'foo@test.com', 'password' => 'pw']);
        $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::MAGIC_LINK->value,
            'secret'  => 'token',
        ]);
        $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::EMAIL_2FA->value,
            'secret'  => '123456',
        ]);

        $rows = $this->identityModel->getIdentitiesByTypes(
            $user,
            [IdentityType::MAGIC_LINK->value, IdentityType::EMAIL_2FA->value],
        );

        $this->assertCount(2, $rows);
    }

    public function testTouchIdentityUpdatesLastUsedAt(): void
    {
        $user = $this->makeUser();

        $id = $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::MAGIC_LINK->value,
            'secret'  => 'tok',
        ], true);

        $identity = $this->identityModel->find($id);
        $this->assertNull($identity->last_used_at);

        $this->identityModel->touchIdentity($identity);

        $reloaded = $this->identityModel->find($id);
        $this->assertNotNull($reloaded->last_used_at);
    }

    public function testRevokeIdentityByIdSetsRevokedAt(): void
    {
        $user = $this->makeUser();

        $id = $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::ACCESS_TOKEN->value,
            'secret'  => hash('sha256', 'rawtok'),
        ], true);

        $this->identityModel->revokeIdentityById((int) $id);

        $row = $this->identityModel->find($id);
        $this->assertNotEmpty($row->revoked_at);
    }

    public function testRevokeIdentitiesByUserAndTypeRevokesOnlyMatchingRows(): void
    {
        $user = $this->makeUser();

        // Token A — matching type, will be revoked
        $a = $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::ACCESS_TOKEN->value,
            'secret'  => hash('sha256', 'a'),
        ], true);

        // Token B — already revoked, should be left alone
        $b = $this->identityModel->insert([
            'user_id'    => $user->id,
            'type'       => IdentityType::ACCESS_TOKEN->value,
            'secret'     => hash('sha256', 'b'),
            'revoked_at' => '2020-01-01 00:00:00',
        ], true);

        // Different type — must be untouched
        $c = $this->identityModel->insert([
            'user_id' => $user->id,
            'type'    => IdentityType::MAGIC_LINK->value,
            'secret'  => 'magic',
        ], true);

        $this->identityModel->revokeIdentitiesByUserAndType((int) $user->id, IdentityType::ACCESS_TOKEN->value);

        $this->assertNotEmpty($this->identityModel->find($a)->revoked_at, 'A must be revoked');
        $this->assertSame('2020-01-01 00:00:00', (string) $this->identityModel->find($b)->revoked_at, 'B revocation date untouched');
        $this->assertEmpty($this->identityModel->find($c)->revoked_at, 'C of different type not affected');
    }

    public function testCreateJwtRefreshTokenAndGet(): void
    {
        $user = $this->makeUser();

        $expires = Time::now()->addDays(7)->format('Y-m-d H:i:s');
        $this->identityModel->createJwtRefreshToken((int) $user->id, 'raw-jwt-token', $expires);

        $found = $this->identityModel->getJwtRefreshToken((int) $user->id, 'raw-jwt-token');
        $this->assertInstanceOf(UserIdentity::class, $found);
        $this->assertSame(IdentityType::JWT_REFRESH->value, $found->type);
    }

    public function testGetJwtRefreshTokenReturnsNullForExpiredOrRevoked(): void
    {
        $user    = $this->makeUser();
        $expired = Time::now()->subDays(1)->format('Y-m-d H:i:s');

        $this->identityModel->createJwtRefreshToken((int) $user->id, 'expired-tok', $expired);

        $this->assertNotInstanceOf(UserIdentity::class, $this->identityModel->getJwtRefreshToken((int) $user->id, 'expired-tok'));

        // Insert revoked one
        $this->identityModel->insert([
            'user_id'    => $user->id,
            'type'       => IdentityType::JWT_REFRESH->value,
            'secret'     => hash('sha256', 'revoked'),
            'expires'    => Time::now()->addDays(7)->format('Y-m-d H:i:s'),
            'revoked_at' => Time::now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertNotInstanceOf(UserIdentity::class, $this->identityModel->getJwtRefreshToken((int) $user->id, 'revoked'));
    }

    public function testForceMultiplePasswordResetSetsForceResetFlag(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->identityModel->createEmailIdentity($a, ['email' => 'a@example.com', 'password' => 'pw1']);
        $this->identityModel->createEmailIdentity($b, ['email' => 'b@example.com', 'password' => 'pw2']);

        $this->identityModel->forceMultiplePasswordReset([(int) $a->id, (int) $b->id]);

        foreach ([(int) $a->id, (int) $b->id] as $userId) {
            $row = $this->identityModel
                ->where('user_id', $userId)
                ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
                ->first();

            $this->assertSame(1, (int) $row->force_reset);
        }
    }

    public function testForceGlobalPasswordResetTouchesAllPasswordIdentities(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->identityModel->createEmailIdentity($a, ['email' => 'a@example.com', 'password' => 'pw1']);
        $this->identityModel->createEmailIdentity($b, ['email' => 'b@example.com', 'password' => 'pw2']);

        $this->identityModel->forceGlobalPasswordReset();

        $rows = $this->identityModel->where('type', Session::ID_TYPE_EMAIL_PASSWORD)->findAll();
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row->force_reset);
        }
    }

    public function testGetAccessTokenByRawTokenIgnoresRevoked(): void
    {
        $user  = $this->makeUser();
        $token = $this->identityModel->generateAccessToken($user, 'mobile');

        $this->assertInstanceOf(AccessToken::class, $this->identityModel->getAccessTokenByRawToken($token->raw_token));

        $this->identityModel->revokeIdentityById((int) $token->id);

        $this->assertNotInstanceOf(AccessToken::class, $this->identityModel->getAccessTokenByRawToken($token->raw_token));
    }
}

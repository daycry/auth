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

namespace Daycry\Auth\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Validation\ValidationInterface;
use Daycry\Auth\Authentication\Authenticators\AccessToken;
use Daycry\Auth\Entities\AccessToken as AccessTokenIdentity;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\DatabaseException;
use Daycry\Exceptions\Exceptions\LogicException;
use Faker\Generator;

class UserIdentityModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = UserIdentity::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'type',
        'secret',
        'secret2',
        'extra',
        'expires',
        'force_reset',
        'ignore_limits',
        'is_private',
        'ip_addresses',
        'last_used_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = $this->tables['identities'];
    }

    /**
     * Inserts a record
     *
     * @param array|object $data
     *
     * @throws DatabaseException
     */
    public function create($data): void
    {
        $this->disableDBDebug();

        $return = $this->insert($data);

        $this->checkQueryReturn($return);
    }

    /**
     * Creates a new identity for this user with an email/password
     * combination.
     *
     * @phpstan-param array{email: string, password: string} $credentials
     */
    public function createEmailIdentity(User $user, array $credentials): void
    {
        $this->checkUserId($user);

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        $return = $this->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret'  => $credentials['email'],
            'secret2' => $passwords->hash($credentials['password']),
        ]);

        $this->checkQueryReturn($return);
    }

    private function checkUserId(User $user): void
    {
        if ($user->id === null) {
            throw new LogicException(
                '"$user->id" is null. You should not use the incomplete User object.'
            );
        }
    }

    /**
     * Create an identity with 6 digits code for auth action
     *
     * @phpstan-param array{type: string, name: string, extra: string} $data
     * @param callable $codeGenerator generate secret code
     *
     * @return string secret
     */
    public function createCodeIdentity(
        User $user,
        array $data,
        callable $codeGenerator
    ): string {
        $this->checkUserId($user);

        helper('text');

        // Create an identity for the action
        $maxTry          = 5;
        $data['user_id'] = $user->id;

        while (true) {
            $data['secret'] = $codeGenerator();

            try {
                $this->create($data);

                break;
            } catch (DatabaseException $e) {
                $maxTry--;

                if ($maxTry === 0) {
                    throw $e;
                }
            }
        }

        return $data['secret'];
    }

    /**
     * Generates a new personal access token for the user.
     *
     * @param string   $name   Token name
     * @param string[] $scopes Permissions the token grants
     */
    public function generateAccessToken(User $user, string $name, array $scopes = ['*']): AccessTokenIdentity
    {
        $this->checkUserId($user);

        helper('text');

        $return = $this->insert([
            'type'    => AccessToken::ID_TYPE_ACCESS_TOKEN,
            'user_id' => $user->id,
            'name'    => $name,
            'secret'  => hash('sha256', $rawToken = random_string('crypto', 64)),
            'extra'   => serialize($scopes),
        ]);

        $this->checkQueryReturn($return);

        /** @var AccessTokenIdentity $token */
        $token = $this
            ->asObject(AccessTokenIdentity::class)
            ->find($this->getInsertID());

        $token->raw_token = $rawToken;

        return $token;
    }

    public function getAccessTokenByRawToken(string $rawToken): ?AccessTokenIdentity
    {
        return $this
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', hash('sha256', $rawToken))
            ->asObject(AccessTokenIdentity::class)
            ->first();
    }

    public function getAccessToken(User $user, string $rawToken): ?AccessTokenIdentity
    {
        $this->checkUserId($user);

        return $this->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', hash('sha256', $rawToken))
            ->asObject(AccessTokenIdentity::class)
            ->first();
    }

    /**
     * Given the ID, returns the given access token.
     *
     * @param int|string $id
     */
    public function getAccessTokenById($id, User $user): ?AccessTokenIdentity
    {
        $this->checkUserId($user);

        return $this->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('id', $id)
            ->asObject(AccessTokenIdentity::class)
            ->first();
    }

    /**
     * @return AccessTokenIdentity[]
     */
    public function getAllAccessToken(User $user): array
    {
        $this->checkUserId($user);

        return $this
            ->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->orderBy($this->primaryKey)
            ->asObject(AccessTokenIdentity::class)
            ->findAll();
    }

    /**
     * Used by 'magic-link'.
     */
    public function getIdentityBySecret(string $type, ?string $secret): ?UserIdentity
    {
        if ($secret === null) {
            return null;
        }

        return $this->where('type', $type)
            ->where('secret', $secret)
            ->first();
    }

    /**
     * Returns all identities.
     *
     * @return UserIdentity[]
     */
    public function getIdentities(User $user): array
    {
        $this->checkUserId($user);

        return $this->where('user_id', $user->id)->orderBy($this->primaryKey)->findAll();
    }

    /**
     * @param int[]|string[] $userIds
     *
     * @return UserIdentity[]
     */
    public function getIdentitiesByUserIds(array $userIds): array
    {
        return $this->whereIn('user_id', $userIds)->orderBy($this->primaryKey)->findAll();
    }

    /**
     * Returns the first identity of the type.
     */
    public function getIdentityByType(User $user, string $type): ?UserIdentity
    {
        $this->checkUserId($user);

        return $this->where('user_id', $user->id)
            ->where('type', $type)
            ->orderBy($this->primaryKey)
            ->first();
    }

    /**
     * Returns all identities for the specific types.
     *
     * @param string[] $types
     *
     * @return UserIdentity[]
     */
    public function getIdentitiesByTypes(User $user, array $types): array
    {
        $this->checkUserId($user);

        if ($types === []) {
            return [];
        }

        return $this->where('user_id', $user->id)
            ->whereIn('type', $types)
            ->orderBy($this->primaryKey)
            ->findAll();
    }

    /**
     * Update the last used at date for an identity record.
     */
    public function touchIdentity(UserIdentity $identity): void
    {
        $identity->last_used_at = Time::now()->format('Y-m-d H:i:s');

        $return = $this->save($identity);

        $this->checkQueryReturn($return);
    }

    public function deleteIdentitiesByType(User $user, string $type): void
    {
        $this->checkUserId($user);

        $return = $this->where('user_id', $user->id)
            ->where('type', $type)
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * Delete any access tokens for the given raw token.
     */
    public function revokeAccessToken(User $user, string $rawToken): void
    {
        $this->checkUserId($user);

        $return = $this->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', hash('sha256', $rawToken))
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * Delete any access tokens for the given secret token.
     */
    public function revokeAccessTokenBySecret(User $user, string $secretToken): void
    {
        $this->checkUserId($user);

        $return = $this->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $secretToken)
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * Revokes all access tokens for this user.
     */
    public function revokeAllAccessToken(User $user): void
    {
        $this->checkUserId($user);

        $return = $this->where('user_id', $user->id)
            ->where('type', AccessToken::ID_TYPE_ACCESS_TOKEN)
            ->delete();

        $this->checkQueryReturn($return);
    }

    /**
     * Force password reset for multiple users.
     *
     * @param int[]|string[] $userIds
     */
    public function forceMultiplePasswordReset(array $userIds): void
    {
        $this->where(['type' => Session::ID_TYPE_EMAIL_PASSWORD, 'force_reset' => 0]);
        $this->whereIn('user_id', $userIds);
        $this->set('force_reset', 1);
        $return = $this->update();

        $this->checkQueryReturn($return);
    }

    /**
     * Force global password reset.
     * This is useful for enforcing a password reset
     * for ALL users in case of a security breach.
     */
    public function forceGlobalPasswordReset(): void
    {
        $whereFilter = [
            'type'        => Session::ID_TYPE_EMAIL_PASSWORD,
            'force_reset' => 0,
        ];
        $this->where($whereFilter);
        $this->set('force_reset', 1);
        $return = $this->update();

        $this->checkQueryReturn($return);
    }

    /**
     * Override the Model's `update()` method.
     * Throws an Exception when it fails.
     *
     * @param array|int|string|null $id
     * @param array|object|null     $data
     *
     * @return true if the update is successful
     *
     * @throws ValidationException
     */
    public function update($id = null, $data = null): bool
    {
        $result = parent::update($id, $data);

        $this->checkQueryReturn($result);

        return true;
    }

    public function fake(Generator &$faker): UserIdentity
    {
        return new UserIdentity([
            'user_id'      => fake(UserModel::class)->id,
            'type'         => Session::ID_TYPE_EMAIL_PASSWORD,
            'name'         => null,
            'secret'       => $faker->unique()->email(),
            'secret2'      => password_hash('secret', PASSWORD_DEFAULT),
            'expires'      => null,
            'extra'        => null,
            'force_reset'  => false,
            'last_used_at' => null,
        ]);
    }
}

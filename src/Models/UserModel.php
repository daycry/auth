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

use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\InvalidArgumentException;
use Faker\Generator;

/**
 * @phpstan-consistent-constructor
 */
class UserModel extends BaseModel
{
    protected $table;
    protected $primaryKey     = 'id';
    protected $returnType     = User::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    protected $afterFind     = ['fetchIdentities'];
    protected $afterInsert   = ['saveEmailIdentity'];
    protected $afterUpdate   = ['saveEmailIdentity'];

    /**
     * Whether identity records should be included
     * when user records are fetched from the database.
     */
    protected bool $fetchIdentities = false;

    /**
     * Save the User for afterInsert and afterUpdate
     */
    protected ?User $tempUser = null;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['users'];
    }

    /**
     * Mark the next find* query to include identities
     *
     * @return $this
     */
    public function withIdentities(): self
    {
        $this->fetchIdentities = true;

        return $this;
    }

    /**
     * Populates identities for all records
     * returned from a find* method. Called
     * automatically when $this->fetchIdentities == true
     *
     * Model event callback called by `afterFind`.
     */
    protected function fetchIdentities(array $data): array
    {
        if (! $this->fetchIdentities) {
            return $data;
        }

        $userIds = $data['singleton']
            ? array_column($data, 'id')
            : array_column($data['data'], 'id');

        if ($userIds === []) {
            return $data;
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Get our identities for all users
        $identities = $identityModel->getIdentitiesByUserIds($userIds);

        if (empty($identities)) {
            return $data;
        }

        $mappedUsers = $this->assignIdentities($data, $identities);

        $data['data'] = $data['singleton'] ? $mappedUsers[array_column($data, 'id')[0]] : $mappedUsers;

        return $data;
    }

    /**
     * Map our users by ID to make assigning simpler
     *
     * @param array              $data       Event $data
     * @param list<UserIdentity> $identities
     *
     * @return         list<User>              UserId => User object
     * @phpstan-return array<int|string, User> UserId => User object
     */
    private function assignIdentities(array $data, array $identities): array
    {
        $mappedUsers    = [];
        $userIdentities = [];

        $users = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($users as $user) {
            $mappedUsers[$user->id] = $user;
        }
        unset($users);

        // Now group the identities by user
        foreach ($identities as $identity) {
            $userIdentities[$identity->user_id][] = $identity;
        }
        unset($identities);

        // Now assign the identities to the user
        foreach ($userIdentities as $userId => $identityArray) {
            $mappedUsers[$userId]->identities = $identityArray;
        }
        unset($userIdentities);

        return $mappedUsers;
    }

    /**
     * Activate a User.
     */
    public function activate(User $user): void
    {
        $user->active = true;

        $this->save($user);
    }

    /**
     * Override the BaseModel's `insert()` method.
     * If you pass User object, also inserts Email Identity.
     *
     * @param array|User $data
     *
     * @return int|string|true Insert ID if $returnID is true
     *
     * @throws ValidationException
     */
    public function insert($data = null, bool $returnID = true)
    {
        // Clone User object for not changing the passed object.
        $this->tempUser = $data instanceof User ? clone $data : null;

        $result = parent::insert($data, $returnID);

        $this->checkQueryReturn($result);

        return $returnID ? $this->insertID : $result;
    }

    /**
     * Override the BaseModel's `update()` method.
     * If you pass User object, also updates Email Identity.
     *
     * @param array|int|string|null $id
     * @param array|User            $data
     *
     * @return true if the update is successful
     *
     * @throws ValidationException
     */
    public function update($id = null, $data = null): bool
    {
        // Clone User object for not changing the passed object.
        $this->tempUser = $data instanceof User ? clone $data : null;

        try {
            /** @throws DataException */
            $result = parent::update($id, $data);
        } catch (DataException $e) {
            // When $data is an array.
            if ($this->tempUser === null) {
                throw $e;
            }

            $messages = [
                lang('Database.emptyDataset', ['update']),
            ];

            if (in_array($e->getMessage(), $messages, true)) {
                $this->tempUser->saveEmailIdentity();

                return true;
            }

            throw $e;
        }

        $this->checkQueryReturn($result);

        return true;
    }

    /**
     * Override the BaseModel's `save()` method.
     * If you pass User object, also updates Email Identity.
     *
     * @param array|User $data
     *
     * @return true if the save is successful
     *
     * @throws ValidationException
     */
    public function save($data): bool
    {
        $result = parent::save($data);

        $this->checkQueryReturn($result);

        return true;
    }

    /**
     * Save Email Identity
     *
     * Model event callback called by `afterInsert` and `afterUpdate`.
     */
    protected function saveEmailIdentity(array $data): array
    {
        // If insert()/update() gets an array data, do nothing.
        if ($this->tempUser === null) {
            return $data;
        }

        // Insert
        if ($this->tempUser->id === null) {
            /** @var User $user */
            $user = $this->find($this->db->insertID());

            // If you get identity (email/password), the User object must have the id.
            $this->tempUser->id = $user->id;

            $user->email         = $this->tempUser->email ?? '';
            $user->password      = $this->tempUser->password ?? '';
            $user->password_hash = $this->tempUser->password_hash ?? '';

            $user->saveEmailIdentity();
            $this->tempUser = null;

            return $data;
        }

        // Update
        $this->tempUser->saveEmailIdentity();
        $this->tempUser = null;

        return $data;
    }

    /**
     * Adds a user to the default group.
     * Used during registration.
     */
    public function addToDefaultGroup(User $user): void
    {
        $defaultGroup = setting('Auth.defaultGroup');

        $rows = model(GroupModel::class)->findAll();

        $allowedGroups = array_column($rows, 'name');

        if (empty($defaultGroup) || ! in_array($defaultGroup, $allowedGroups, true)) {
            throw new InvalidArgumentException(lang('Auth.unknownGroup', [$defaultGroup ?? '--not found--']));
        }

        $user->addGroup($defaultGroup);
    }

    public function fake(Generator &$faker): User
    {
        return new User([
            'username' => $faker->unique()->userName(),
            'active'   => true,
        ]);
    }

    /**
     * Locates a User object by ID.
     *
     * @param int|string $id
     */
    public function findById($id): ?User
    {
        return $this->find($id);
    }

    /**
     * Locate a User object by the given credentials.
     *
     * @param array<string, string> $credentials
     */
    public function findByCredentials(array $credentials): ?User
    {
        // Email is stored in an identity so remove that here
        $email = $credentials['email'] ?? null;
        unset($credentials['email']);

        if ($email === null && $credentials === []) {
            return null;
        }

        // any of the credentials used should be case-insensitive
        foreach ($credentials as $key => $value) {
            $this->where(
                'LOWER(' . $this->db->protectIdentifiers($this->table . ".{$key}") . ')',
                strtolower($value),
            );
        }

        if ($email !== null) {
            /** @var array<string, int|string|null>|null $data */
            $data = $this->select(
                sprintf('%1$s.*, %2$s.secret as email, %2$s.secret2 as password_hash', $this->table, $this->tables['identities']),
            )
                ->join($this->tables['identities'], sprintf('%1$s.user_id = %2$s.id', $this->tables['identities'], $this->table))
                ->where($this->tables['identities'] . '.type', Session::ID_TYPE_EMAIL_PASSWORD)
                ->where(
                    'LOWER(' . $this->db->protectIdentifiers($this->tables['identities'] . '.secret') . ')',
                    strtolower($email),
                )
                ->asArray()
                ->first();

            if ($data === null) {
                return null;
            }

            $email = $data['email'];
            unset($data['email']);
            $password_hash = $data['password_hash'];
            unset($data['password_hash']);

            $user                = new User($data);
            $user->email         = $email;
            $user->password_hash = $password_hash;
            $user->syncOriginal();

            return $user;
        }

        return $this->first();
    }

    /**
     * Updates the user's last active date.
     */
    public function updateActiveDate(User $user): void
    {
        assert($user->last_active instanceof Time);

        // Safe date string for database
        $last_active = $user->last_active->format('Y-m-d H:i:s');

        $this->builder()
            ->set('last_active', $last_active)
            ->where('id', $user->id)
            ->update();
    }
}

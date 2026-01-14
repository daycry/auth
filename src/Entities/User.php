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

namespace Daycry\Auth\Entities;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Traits\Activatable;
use Daycry\Auth\Traits\Authorizable;
use Daycry\Auth\Traits\Bannable;
use Daycry\Auth\Traits\HasAccessTokens;
use Daycry\Auth\Traits\Resettable;

class User extends Entity
{
    use Authorizable;
    use Bannable;
    use Activatable;
    use Resettable;
    use HasAccessTokens;

    /**
     * @var list<UserIdentity>|null
     */
    private ?array $identities = null;

    private ?string $email         = null;
    private ?string $password      = null;
    private ?string $password_hash = null;

    /**
     * @var         list<string>
     * @phpstan-var list<string>
     * @psalm-var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'last_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id'          => '?integer',
        'active'      => 'int_bool',
        'permissions' => 'array',
        'groups'      => 'array',
    ];

    /**
     * Returns the first identity of the given $type for this user.
     *
     * @param string $type See IdentityType enum.
     */
    public function getIdentity(string $type): ?UserIdentity
    {
        $identities = $this->getIdentities($type);

        return count($identities) ? array_shift($identities) : null;
    }

    /**
     * Accessor method for this user's UserIdentity objects.
     * Will populate if they don't exist.
     *
     * @param string $type 'all' returns all identities.
     *
     * @return list<UserIdentity>
     */
    public function getIdentities(string $type = 'all'): array
    {
        $this->populateIdentities();

        if ($type === 'all') {
            return $this->identities;
        }

        $identities = [];

        foreach ($this->identities as $identity) {
            if ($identity->type === $type) {
                $identities[] = $identity;
            }
        }

        return $identities;
    }

    /**
     * ensures that all of the user's identities are loaded
     * into the instance for faster access later.
     */
    private function populateIdentities(): void
    {
        if ($this->identities === null) {
            /** @var UserIdentityModel $identityModel */
            $identityModel    = model(UserIdentityModel::class);
            $this->identities = $identityModel->getIdentities($this);
        }
    }

    public function setIdentities(array $identities): void
    {
        $this->identities = $identities;
    }

    /**
     * Returns the user's Email/Password identity.
     */
    public function getEmailIdentity(): ?UserIdentity
    {
        return $this->getIdentity(IdentityType::EMAIL_PASSWORD->value);
    }

    /**
     * Accessor method to grab the user's email address.
     * Will cache it in $this->email, since it has
     * to hit the database the first time to get it, most likely.
     */
    public function getEmail(): ?string
    {
        if ($this->email === null) {
            $this->email = $this->getEmailIdentity()->secret ?? null;
        }

        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): User
    {
        $this->password = $password;

        return $this;
    }

    public function setPasswordHash(string $hash): User
    {
        $this->password_hash = $hash;

        return $this;
    }

    /**
     * Accessor method to grab the user's password hash.
     * Will cache it in $this->attributes, since it has
     * to hit the database the first time to get it, most likely.
     */
    public function getPasswordHash(): ?string
    {
        if ($this->password_hash === null) {
            $this->password_hash = $this->getEmailIdentity()->secret2 ?? null;
        }

        return $this->password_hash;
    }
}

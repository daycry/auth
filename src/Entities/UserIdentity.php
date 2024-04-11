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
use Daycry\Auth\Authentication\Passwords;

/**
 * Class UserIdentity
 *
 * Represents a single set of user identity credentials.
 * For the base Shield system, this would be one of the following:
 *  - password
 *  - reset hash
 *  - access token
 *
 * This can also be used to store credentials for social logins,
 * OAUTH or JWT tokens, etc. A user can have multiple of each,
 * though a Authenticator may want to enforce only one exists for that
 * user, like a password.
 *
 * @property string|Time|null $last_used_at
 * @property string|null      $secret
 * @property string|null      $secret2
 */
class UserIdentity extends Entity
{
    private ?User $user = null;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id'          => '?integer',
        'force_reset' => 'int_bool',
    ];

    /**
     * @var         list<string>
     * @phpstan-var list<string>
     * @psalm-var list<string>
     */
    protected $dates = [
        'expires',
        'last_used_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Uses password-strength hashing to hash
     * a given value for the 'secret'.
     */
    public function hashSecret(string $value): UserIdentity
    {
        /** @var Passwords $passwords */
        $passwords = service('passwords');

        $this->attributes['secret'] = $passwords->hash($value);

        return $this;
    }

    /**
     * Returns the user associated with this token.
     */
    public function user(): ?User
    {
        if ($this->user === null) {
            $users      = auth()->getProvider();
            $this->user = $users->findById($this->user_id);
        }

        return $this->user;
    }
}

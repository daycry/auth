<?php

declare(strict_types=1);

namespace Daycry\Auth\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;

/**
 * Class AccessToken
 *
 * Represents a single Personal Access Token, used
 * for authenticating users for an API.
 *
 * @property string|Time|null $last_used_at
 */
class AccessToken extends UserIdentity
{
    /**
     * @var array<string, string>
     */
    protected $datamap = [
        'scopes' => 'extra',
    ];

    /**
     * Determines whether this token grants
     * permission to the $scope
     */
    public function can(string $scope): bool
    {
        if ($this->extra === []) {
            return false;
        }

        // Wildcard present
        if (in_array('*', $this->extra, true)) {
            return true;
        }

        // Check stored scopes
        return in_array($scope, $this->extra, true);
    }

    /**
     * Determines whether this token does NOT
     * grant permission to $scope.
     */
    public function cant(string $scope): bool
    {
        return ! $this->can($scope);
    }
}
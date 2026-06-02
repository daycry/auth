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

namespace Tests\Authorization;

use Daycry\Auth\Models\PermissionModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * Covers the Gate → RBAC fallback bridge: a dotted ability with no registered
 * closure/policy falls back to the user's RBAC permissions.
 *
 * @internal
 */
final class GateRbacBridgeTest extends DatabaseTestCase
{
    use FakeUser;

    protected $refresh = true;

    public function testGateFallsBackToRbacPermission(): void
    {
        fake(PermissionModel::class, ['name' => 'posts.edit']);
        $this->user->addPermission('posts.edit');

        $gate = service('gate')->forUser($this->user);

        // No closure/policy is registered for these abilities, so the Gate
        // defers to RBAC.
        $this->assertTrue($gate->allows('posts.edit'));
        $this->assertFalse($gate->allows('posts.delete'));
    }

    public function testGateRbacFallbackCanBeDisabled(): void
    {
        fake(PermissionModel::class, ['name' => 'posts.edit']);
        $this->user->addPermission('posts.edit');

        setting('AuthSecurity.gateFallbackToRbac', false);

        $gate = service('gate')->forUser($this->user);

        $this->assertFalse($gate->allows('posts.edit'));
    }

    public function testNonDottedUnknownAbilityStillDenied(): void
    {
        $gate = service('gate')->forUser($this->user);

        // Abilities without a scope are never RBAC permissions → denied.
        $this->assertFalse($gate->allows('superpower'));
    }
}

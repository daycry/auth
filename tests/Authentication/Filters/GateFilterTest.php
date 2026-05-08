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
use Daycry\Auth\Authorization\Gate;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Filters\GateFilter;
use Daycry\Auth\Models\UserModel;
use Tests\Support\FilterTestCase;

/**
 * @internal
 */
final class GateFilterTest extends FilterTestCase
{
    protected string $alias       = 'gate';
    protected string $classname   = GateFilter::class;
    protected string $routeFilter = 'gate:dashboard.access';

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];
    }

    public function testRejectsWhenAbilityNotRegistered(): void
    {
        $user = fake(UserModel::class);

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        // No gate defined → denies.
        $this->assertNotSame(200, $result->response()->getStatusCode());
    }

    public function testAllowsWhenGateReturnsTrue(): void
    {
        /** @var Gate $gate */
        $gate = service('gate');
        $gate->define('dashboard.access', static fn (?User $u): bool => $u !== null);

        $user = fake(UserModel::class);

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');
    }

    public function testRejectsWhenGateReturnsFalse(): void
    {
        /** @var Gate $gate */
        $gate = service('gate');
        $gate->define('dashboard.access', static fn (?User $u): bool => false);

        $user = fake(UserModel::class);

        $result = $this->withSession(['user' => ['id' => $user->id]])
            ->get('protected-route');

        $this->assertNotSame(200, $result->response()->getStatusCode());
    }
}

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

use Daycry\Auth\Authorization\AuthorizationException;
use Daycry\Auth\Authorization\Gate;
use Daycry\Auth\Authorization\Policy;
use Daycry\Auth\Authorization\PolicyResponse;
use Daycry\Auth\Entities\User;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class GateTest extends TestCase
{
    private Gate $gate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new Gate();
    }

    // ---------------- Closure-based abilities --------------------------

    public function testClosureAllowsWhenReturnsTrue(): void
    {
        $this->gate->define('do.it', static fn (?User $user): bool => true);

        $this->assertTrue($this->gate->allows('do.it'));
        $this->assertFalse($this->gate->denies('do.it'));
    }

    public function testClosureDeniesWhenReturnsFalse(): void
    {
        $this->gate->define('do.it', static fn (?User $user): bool => false);

        $this->assertFalse($this->gate->allows('do.it'));
        $this->assertTrue($this->gate->denies('do.it'));
    }

    public function testUnknownAbilityDenies(): void
    {
        $this->assertFalse($this->gate->allows('not.registered'));
        $this->assertTrue($this->gate->denies('not.registered'));
    }

    public function testClosureReceivesArguments(): void
    {
        $this->gate->define(
            'compare',
            static fn (?User $user, mixed ...$args): bool => $args[0] < $args[1],
        );

        $this->assertTrue($this->gate->allows('compare', 1, 2));
        $this->assertFalse($this->gate->allows('compare', 5, 2));
    }

    public function testForUserScopesCheck(): void
    {
        $alice         = new User();
        $alice->id     = 1;
        $alice->active = true;

        $bob         = new User();
        $bob->id     = 2;
        $bob->active = true;

        $this->gate->define(
            'self.access',
            static fn (?User $user, mixed ...$args): bool => $user !== null && $user->id === $args[0],
        );

        $this->assertTrue($this->gate->forUser($alice)->allows('self.access', 1));
        $this->assertFalse($this->gate->forUser($bob)->allows('self.access', 1));
    }

    public function testHasRecognisesRegisteredAbility(): void
    {
        $this->gate->define('foo', static fn (?User $user): bool => true);

        $this->assertTrue($this->gate->has('foo'));
        $this->assertFalse($this->gate->has('bar'));
    }

    // ---------------- PolicyResponse + authorize() --------------------

    public function testAuthorizeReturnsResponseWhenAllowed(): void
    {
        $this->gate->define(
            'allowed',
            static fn (?User $user): PolicyResponse => PolicyResponse::allow('OK'),
        );

        $response = $this->gate->authorize('allowed');

        $this->assertTrue($response->allowed());
        $this->assertSame('OK', $response->message());
    }

    public function testAuthorizeThrowsOnDeny(): void
    {
        $this->gate->define(
            'forbidden',
            static fn (?User $user): PolicyResponse => PolicyResponse::deny('Nope.'),
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Nope.');

        $this->gate->authorize('forbidden');
    }

    public function testAuthorizeThrowsOnFalseReturn(): void
    {
        $this->gate->define('plain', static fn (?User $user): bool => false);

        $this->expectException(AuthorizationException::class);

        $this->gate->authorize('plain');
    }

    public function testAuthorizeReturnsAllowResponseOnTrueReturn(): void
    {
        $this->gate->define('plain', static fn (?User $user): bool => true);

        $response = $this->gate->authorize('plain');

        $this->assertTrue($response->allowed());
    }

    // ---------------- Class-based policies ----------------------------

    public function testPolicyMethodResolvesByAbilitySuffix(): void
    {
        $this->gate->policy(GateTestResource::class, GateTestResourcePolicy::class);

        $resource          = new GateTestResource();
        $resource->ownerId = 42;

        $owner     = new User();
        $owner->id = 42;

        $stranger     = new User();
        $stranger->id = 99;

        $this->assertTrue($this->gate->forUser($owner)->allows('post.update', $resource));
        $this->assertFalse($this->gate->forUser($stranger)->allows('post.update', $resource));
    }

    public function testPolicyBeforeShortCircuits(): void
    {
        $this->gate->policy(GateTestResource::class, GateTestAdminPolicy::class);

        $admin                = new User();
        $admin->id            = 7;
        $admin->is_test_admin = true;

        $resource          = new GateTestResource();
        $resource->ownerId = 42;

        // The action method denies, but before() returns true → bypass.
        $this->assertTrue($this->gate->forUser($admin)->allows('update', $resource));
    }

    public function testPolicyDenyResponseProducesAuthorizationException(): void
    {
        $this->gate->policy(GateTestResource::class, GateTestResourcePolicy::class);

        $resource          = new GateTestResource();
        $resource->ownerId = 42;

        $stranger     = new User();
        $stranger->id = 1;

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Only the owner.');

        $this->gate->forUser($stranger)->authorize('post.delete', $resource);
    }
}

// ---------------- Test fixtures ---------------------------------------

class GateTestResource
{
    public ?int $ownerId = null;
}

class GateTestResourcePolicy extends Policy
{
    public function update(?User $user, GateTestResource $resource): bool
    {
        return $user !== null && $user->id === $resource->ownerId;
    }

    public function delete(?User $user, GateTestResource $resource): PolicyResponse
    {
        if ($user === null || $user->id !== $resource->ownerId) {
            return PolicyResponse::deny('Only the owner.');
        }

        return PolicyResponse::allow();
    }
}

class GateTestAdminPolicy extends Policy
{
    public function before(?User $user, string $ability, array $arguments): bool|PolicyResponse|null
    {
        if ($user !== null && ($user->is_test_admin ?? false) === true) {
            return true;
        }

        return null;
    }

    public function update(?User $user, GateTestResource $resource): bool
    {
        return false; // delegated; only `before()` allows admins through
    }
}

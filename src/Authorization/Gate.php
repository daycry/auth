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

namespace Daycry\Auth\Authorization;

use Closure;
use Daycry\Auth\Entities\User;

/**
 * Authorization gateway — the entry point for closure- or class-based
 * authorization rules. Inspired by Laravel's `Gate` facade.
 *
 * Three styles of rules are supported, in this resolution order:
 *
 *  1. **Closure rules** registered via {@see define()}.
 *  2. **Class-based policies** registered via {@see policy()} or
 *     auto-discovered for a resource class via the configured
 *     `policyNamespace` (defaults to `App\Policies\\`).
 *  3. Anything else: the rule is treated as undefined and the check
 *     denies by default.
 *
 * Use:
 *
 *     // Register a closure rule:
 *     Gate::define('post.update', fn ($user, Post $post) =>
 *         $user !== null && $user->id === $post->author_id);
 *
 *     // Or attach a policy class:
 *     Gate::policy(Post::class, PostPolicy::class);
 *     // → Gate::allows('update', $post) dispatches to PostPolicy::update()
 *
 *     // Check:
 *     if (Gate::allows('post.update', $post)) { ... }
 *     // Or fail-fast:
 *     Gate::authorize('post.delete', $post); // throws AuthorizationException
 */
class Gate
{
    /**
     * @var array<string, Closure>
     */
    private array $abilities = [];

    /**
     * @var array<class-string, class-string>
     */
    private array $policies = [];

    /**
     * Optional override for the user against whom checks resolve. When
     * null, falls back to the currently logged-in user.
     */
    private ?User $forUser = null;

    /**
     * Registers a closure-based ability rule.
     *
     * @param Closure(User|null, mixed...): (bool|PolicyResponse) $callback
     */
    public function define(string $ability, Closure $callback): self
    {
        $this->abilities[$ability] = $callback;

        return $this;
    }

    /**
     * Maps a resource class to a policy class. The policy method matched
     * against the ability name ("update", "delete", etc.) is invoked with
     * the user + the resource instance + any additional arguments.
     *
     * @param class-string $resource
     * @param class-string $policy
     */
    public function policy(string $resource, string $policy): self
    {
        $this->policies[$resource] = $policy;

        return $this;
    }

    /**
     * Returns a Gate instance scoped to the given user. Useful when you
     * need to authorize on behalf of a different user than the one
     * currently logged in (e.g. impersonation, admin operations).
     */
    public function forUser(User $user): self
    {
        $clone          = clone $this;
        $clone->forUser = $user;

        return $clone;
    }

    /**
     * @param mixed ...$arguments
     */
    public function allows(string $ability, ...$arguments): bool
    {
        return $this->resolve($ability, $arguments) === true;
    }

    /**
     * @param mixed ...$arguments
     */
    public function denies(string $ability, ...$arguments): bool
    {
        return ! $this->allows($ability, ...$arguments);
    }

    /**
     * Throws {@see AuthorizationException} when the check fails.
     *
     * @param mixed ...$arguments
     *
     * @throws AuthorizationException
     */
    public function authorize(string $ability, ...$arguments): PolicyResponse
    {
        $result = $this->dispatch($ability, $arguments);

        if ($result instanceof PolicyResponse) {
            return $result->authorize();
        }

        if ($result === true) {
            return PolicyResponse::allow();
        }

        throw new AuthorizationException();
    }

    /**
     * Returns true when the resolved value is strictly true.
     *
     * @param list<mixed> $arguments
     */
    private function resolve(string $ability, array $arguments): bool
    {
        $result = $this->dispatch($ability, $arguments);

        if ($result instanceof PolicyResponse) {
            return $result->allowed();
        }

        return $result === true;
    }

    /**
     * Runs the appropriate rule and returns its raw result.
     *
     * @param list<mixed> $arguments
     *
     * @return bool|PolicyResponse|null
     */
    private function dispatch(string $ability, array $arguments)
    {
        $user = $this->forUser ?? (auth()->loggedIn() ? auth()->user() : null);

        // 1. Closure-based ability registered via define().
        if (isset($this->abilities[$ability])) {
            return ($this->abilities[$ability])($user, ...$arguments);
        }

        // 2. Policy lookup by first argument's class.
        if ($arguments !== []) {
            $resource    = $arguments[0];
            $resourceCls = is_object($resource) ? $resource::class : (is_string($resource) ? $resource : null);

            if ($resourceCls !== null) {
                $policyCls = $this->policies[$resourceCls] ?? $this->autoDiscoverPolicy($resourceCls);

                if ($policyCls !== null && class_exists($policyCls)) {
                    return $this->callPolicy($policyCls, $ability, $user, $arguments);
                }
            }
        }

        // 3. RBAC fallback: a scoped ability (e.g. "users.edit") with no closure
        //    or policy defers to the user's RBAC permissions, so `gate:` and
        //    `permission:` filters share semantics. Toggle via gateFallbackToRbac.
        if (
            $user instanceof User
            && str_contains($ability, '.')
            && (bool) (setting('AuthSecurity.gateFallbackToRbac') ?? true)
        ) {
            return $user->can($ability);
        }

        return null; // unknown ability → deny
    }

    /**
     * @param class-string $policyCls
     * @param list<mixed>  $arguments
     *
     * @return bool|PolicyResponse|null
     */
    private function callPolicy(string $policyCls, string $ability, ?User $user, array $arguments)
    {
        $policy = new $policyCls();

        if ($policy instanceof Policy) {
            $before = $policy->before($user, $ability, $arguments);

            if ($before !== null) {
                return $before;
            }
        }

        // Ability names like "post.update" map to method "update";
        // bare names map directly.
        $method = str_contains($ability, '.') ? substr($ability, strrpos($ability, '.') + 1) : $ability;

        if (! method_exists($policy, $method)) {
            return null;
        }

        return $policy->{$method}($user, ...$arguments);
    }

    /**
     * Convention: `App\Models\Post` → `App\Policies\PostPolicy`.
     *
     * @param class-string $resourceCls
     *
     * @return class-string|null
     */
    private function autoDiscoverPolicy(string $resourceCls): ?string
    {
        $config = config('Auth');

        if (! (bool) $config->gateAutoDiscover) {
            return null;
        }

        $namespace = (string) $config->policyNamespace;
        $shortName = substr($resourceCls, strrpos($resourceCls, '\\') + 1);
        $candidate = $namespace . $shortName . 'Policy';

        return class_exists($candidate) ? $candidate : null;
    }

    /**
     * Returns true when an ability is registered as a closure (does not
     * include policy methods, which are discovered lazily on resolve).
     */
    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }
}

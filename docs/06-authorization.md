# 👥 Authorization — Groups & Permissions

Daycry Auth includes a full **Role-Based Access Control (RBAC)** system built on two concepts:

- **Groups** — Named roles (e.g., `admin`, `editor`, `user`)
- **Permissions** — Specific actions (e.g., `posts.create`, `users.delete`)

Both are stored in the database and can be assigned freely to any user.

## 📋 Table of Contents

- [Quick Reference](#quick-reference)
- [Groups](#groups)
- [Permissions](#permissions)
- [Permission Inheritance](#permission-inheritance)
- [Soft-Deleted Records](#soft-deleted-records)
- [Gates & Policies](#gates--policies)
- [Gate → RBAC Bridge](#gate--rbac-bridge)
- [Resolvable Repository Services](#resolvable-repository-services)
- [Authorization in Controllers](#authorization-in-controllers)
- [Authorization in Views](#authorization-in-views)
- [Route Filters](#route-filters)
- [Permission Cache](#permission-cache)
- [Default Group for New Users](#default-group-for-new-users)
- [Admin Panel](#admin-panel)
- [Best Practices](#best-practices)

---

## Quick Reference

```php
// Groups
$user->inGroup('admin');                    // bool
$user->addGroup('editor');                  // void
$user->removeGroup('editor');               // void
$user->getGroups();                         // array of group names

// Permissions
$user->can('posts.create');                 // bool
$user->addPermission('posts.edit');         // void
$user->removePermission('posts.edit');      // void
$user->getPermissions();                    // ?array of permission names

// Multiple groups / permissions
$user->inGroup('admin', 'moderator');       // true if in ANY of them
$user->can('posts.edit', 'posts.delete');   // true if user has ANY of them (OR)
```

---

## Groups

### What Is a Group?

A group is a named role. Users can belong to **multiple groups**. Example groups: `admin`, `editor`, `subscriber`, `premium`.

Groups are stored in the `auth_groups` table. You create them through the Admin Panel or directly via SQL/migrations.

### Checking Group Membership

```php
$user = auth()->user();

// Single group check
if ($user->inGroup('admin')) {
    echo "Welcome, administrator!";
}

// Check multiple groups (true if the user is in ANY of them)
if ($user->inGroup('admin', 'moderator')) {
    echo "Welcome, elevated user!";
}

// Get all groups the user belongs to
$groups = $user->getGroups();
// Returns: ['admin', 'editor']
```

### Assigning and Removing Groups

```php
$user = auth()->user();

// Add to a group
$user->addGroup('editor');
$user->addGroup('premium', 'beta-tester'); // Multiple groups at once

// Remove from a group
$user->removeGroup('editor');

// Check and conditionally assign
if (! $user->inGroup('subscriber')) {
    $user->addGroup('subscriber');
}
```

### Getting All Users in a Group

```php
use Daycry\Auth\Models\GroupModel;

$groupModel = model(GroupModel::class);
$adminGroup = $groupModel->where('name', 'admin')->first();

// Get all users in the admin group
$adminUsers = model(\Daycry\Auth\Models\UserModel::class)
    ->join('auth_groups_users', 'auth_groups_users.user_id = users.id')
    ->join('auth_groups', 'auth_groups.id = auth_groups_users.group_id')
    ->where('auth_groups.name', 'admin')
    ->findAll();
```

---

## Permissions

### Permission Format

Permissions follow a `resource.action` convention:

```text
posts.create
posts.edit
posts.delete
users.view
users.edit
admin.panel
```

Using dots keeps permissions organized and makes wildcard patterns intuitive.

### Checking Permissions

```php
$user = auth()->user();

// Direct check
if ($user->can('posts.create')) {
    // User can create posts
}

// Negation
if (! $user->can('users.delete')) {
    return redirect()->back()->with('error', 'You cannot delete users.');
}

// can() is variadic with OR-semantics — true if the user has ANY of them
if ($user->can('posts.edit', 'posts.delete')) {
    // User can edit OR delete
}

// Need ALL of several permissions? Chain individual checks with &&
if ($user->can('posts.create') && $user->can('posts.publish')) {
    // User can create AND publish
}
```

> **OR-semantics & group-less users**: `can(string ...$permissions)` returns `true`
> as soon as **any one** of the listed permissions is granted. The check no longer
> aborts early for users who belong to no groups — a group-less user with a
> matching **direct** permission is still authorized. Each permission must contain
> a scope and action (e.g. `posts.create`); passing a string without a `.` throws
> a `LogicException`.

### Assigning and Removing Permissions

Permissions can be assigned **directly to a user** (user-level) or **to a group** (group-level, then inherited by all group members).

```php
$user = auth()->user();

// Direct user permissions
$user->addPermission('posts.edit');
$user->addPermission('posts.publish', 'posts.feature'); // Multiple at once

// Remove a permission
$user->removePermission('posts.edit');

// Get all permissions the user has (direct + inherited from groups)
$allPermissions = $user->getPermissions();
```

### Assigning Permissions to a Group

```php
use Daycry\Auth\Models\GroupModel;

$groupModel  = model(GroupModel::class);
$editorGroup = $groupModel->where('name', 'editor')->first();

// Add permission to the group — all editors inherit this
$editorGroup->addPermission('posts.create');
$editorGroup->addPermission('posts.edit');
```

---

## Permission Inheritance

```text
User
 ├── Direct permissions: [posts.delete]
 └── Groups:
      ├── editor  → permissions: [posts.create, posts.edit]
      └── premium → permissions: [posts.feature]

Effective permissions: [posts.delete, posts.create, posts.edit, posts.feature]
```

`$user->can('posts.create')` returns `true` because `editor` group has that permission, and the user is in the `editor` group.

### Wildcard Permissions

`can()` resolves three forms of grant, and matching is **uniform across
user-level (direct) and group-level (inherited) permissions** — a wildcard
works the same whether it was assigned directly to the user or to one of their
groups:

| Granted permission | Matches | Notes |
|--------------------|---------|-------|
| `*` (global wildcard) | every permission | superadmin |
| `posts.*` (scope wildcard) | every `posts.<action>` | per-resource grant |
| `posts.create` (exact) | only `posts.create` | exact match |

Use `*` as a wildcard:

```php
// Grant access to all "posts" permissions — works for DIRECT user permissions too
$user->addPermission('posts.*');

$user->can('posts.create'); // true  (scope wildcard expands to posts.create)
$user->can('posts.edit');   // true
$user->can('posts.delete'); // true
$user->can('users.view');   // false (different resource)
```

```php
// Grant ALL permissions (superadmin)
$user->addPermission('*');

$user->can('posts.delete'); // true
$user->can('users.view');   // true
```

> **Note**: A scope wildcard assigned **directly** to a user (e.g.
> `$user->addPermission('posts.*')`) now correctly grants `posts.create`,
> `posts.edit`, etc. Earlier versions only expanded scope wildcards that were
> inherited from a group; direct wildcards required an exact-string match.

---

## Soft-Deleted Records

Every RBAC table — `auth_groups`, `auth_permissions`, and the three pivots
`auth_groups_users`, `auth_permissions_users`, `auth_permissions_groups` — ships
with a `deleted_at` column. The models hard-delete by default
(`$useSoftDeletes = false`), so that column stays `null`. If you enable
**soft-deletes** (extend a model and set `$useSoftDeletes = true`, or write
`deleted_at` yourself), a soft-deleted row is treated as gone for **every
authorization decision**, exactly like a hard-deleted one:

| Soft-deleted record | Effect |
|---------------------|--------|
| A **group** (`auth_groups`) | drops every member's membership — `inGroup()`, `getGroups()` and its inherited permissions stop counting it; it can no longer be (re-)assigned via `addGroup()` |
| A **permission** (`auth_permissions`) | stops being granted via `can()` / `getPermissions()`, whether assigned directly or inherited, and can no longer be (re-)assigned via `addPermission()` |
| A **pivot row** (`*_users`, `permissions_groups`) | drops just that one assignment — the group / permission itself and every other user stay untouched |

The exclusion is **config-agnostic**: the RBAC read queries filter
`deleted_at IS NULL` unconditionally, so it is a no-op under the default
hard-delete behaviour (the row is already gone) and automatically correct once
soft-deletes are in play. This mirrors how a soft-deleted **user** is locked out
of every authenticator — see
[Authentication → Deleted Users](03-authentication.md#deleted-users).

> **Admin panel**: the management screens still list every record (including
> soft-deleted ones) so they can be reviewed or restored. The exclusion applies
> to authorization *use*, not to administrative listing.

---

## Gates & Policies

The RBAC system above answers questions like *"does this user have permission `posts.update`?"* — **a property of the user only**. For *"is this user allowed to update **this specific post**?"* — a question that depends on the resource — daycry/auth ships a Gate / Policy layer inspired by Laravel's `Gate` facade. Use both: RBAC for static role checks, Gates for context-dependent rules.

### Closure-based abilities

Register a closure to define an ability inline. The first parameter is the authenticated user (or `null` for guests); any further arguments are the resource(s) being checked:

```php
// app/Config/Events.php (or any bootstrap point)
use Daycry\Auth\Authorization\Gate;
use Daycry\Auth\Entities\User;

service('gate')->define('post.update', static function (?User $user, Post $post): bool {
    return $user !== null && $user->id === $post->author_id;
});
```

Then call from anywhere:

```php
if (service('gate')->allows('post.update', $post)) {
    // ...
}

// Negation:
service('gate')->denies('post.update', $post);

// Fail-fast — throws AuthorizationException on a deny:
service('gate')->authorize('post.update', $post);
```

The User entity exposes the same checks as `canDo()` / `cantDo()`:

```php
$user->canDo('post.update', $post);  // bool
$user->cantDo('post.delete', $post); // bool
```

> `canDo()` and `cantDo()` are deliberately distinct from `can()` / `cant()` so the existing RBAC method `can('posts.update')` (string-only permission identifier) keeps its signature.

### Class-based policies

For resources with multiple actions, group the rules into a `Policy` subclass:

```php
namespace App\Policies;

use App\Models\Post;
use Daycry\Auth\Authorization\Policy;
use Daycry\Auth\Authorization\PolicyResponse;
use Daycry\Auth\Entities\User;

class PostPolicy extends Policy
{
    public function update(?User $user, Post $post): bool
    {
        return $user !== null && $user->id === $post->author_id;
    }

    public function delete(?User $user, Post $post): PolicyResponse
    {
        if ($user === null) {
            return PolicyResponse::deny('You must be logged in.');
        }

        return $user->id === $post->author_id
            ? PolicyResponse::allow()
            : PolicyResponse::deny('Only the author can delete this post.');
    }

    /**
     * Optional pre-flight hook. Return true / false / a PolicyResponse to
     * short-circuit the action method. Return null to fall through.
     */
    public function before(?User $user, string $ability, array $arguments): bool|PolicyResponse|null
    {
        if ($user !== null && $user->inGroup('admin')) {
            return true; // admins bypass every action on this resource
        }

        return null;
    }
}
```

#### Auto-discovery

By default, the Gate looks up `App\Policies\{ResourceShortName}Policy` for any resource passed to a check. So `service('gate')->allows('update', $post)` automatically resolves `App\Models\Post` → `App\Policies\PostPolicy::update()`. Override the namespace in `app/Config/Auth.php`:

```php
public string $policyNamespace = 'App\\Authorization\\Policies\\';
public bool $gateAutoDiscover  = true; // false to require explicit registration
```

#### Explicit registration

If your resources don't follow the convention, map them by hand:

```php
service('gate')->policy(\App\Models\Post::class, \App\Policies\PostPolicy::class);
```

#### Action name convention

Ability names ending in `.action` (e.g. `post.update`) dispatch to the action method (`update`). Bare names (e.g. `update`) also work. This lets you namespace abilities for clarity in routes / configs while keeping policy method names short.

### `gate:` route filter

For abilities that depend **only on the authenticated user** (not on a resource instance) — typical for "section-level" gates such as accessing a dashboard or a billing area — apply the `gate:` filter directly:

```php
$routes->get('admin', 'Admin::index', ['filter' => 'gate:admin.access']);

// Multiple abilities (AND):
$routes->get('billing/cancel', 'Billing::cancel', [
    'filter' => 'gate:billing.access,billing.cancel',
]);
```

Failure responses follow the same shape as `permission:` — JSON 403 for API requests, redirect to `Auth::permissionDeniedRedirect()` for web.

For abilities that **need a resource argument** (a `Post`, an `Order`...), use the Gate API directly inside the controller method — the filter cannot reach the resource yet:

```php
public function update(int $id)
{
    $post = $this->postModel->find($id);
    service('gate')->authorize('post.update', $post);  // throws on deny

    // ...persist update...
}
```

### When to use what

| You want to check | Use |
|-------------------|-----|
| Does this user have permission `users.edit`? | RBAC — `$user->can('users.edit')` |
| Does this user belong to the admin group? | RBAC — `$user->inGroup('admin')` |
| Can this user update *this specific post*? | Gate — `$user->canDo('post.update', $post)` |
| Block a route based on the user alone | `permission:` or `group:` filter |
| Block a route based on a resource | `gate:` filter for ability-only checks; controller `Gate::authorize()` for resource-aware checks |

---

## Gate → RBAC Bridge

The Gate and the RBAC permission system can share semantics, so you don't have to
re-implement static role checks as Gate closures. When a Gate check is dispatched
and the ability:

1. has **no** registered closure (`define()`), **and**
2. matches **no** policy method, **and**
3. **contains a scope** (a `.`, e.g. `users.edit`)

…the Gate falls back to `User::can($ability)` against the authenticated user's
RBAC permissions. This is why `gate:users.edit` and `permission:users.edit` can
resolve to the same decision.

### Configuration

```php
// app/Config/AuthSecurity.php
public bool $gateFallbackToRbac = true;
```

| Option | Default | Meaning |
|--------|---------|---------|
| `$gateFallbackToRbac` | `true` | A Gate ability containing a scope (e.g. `users.edit`) with no registered closure/policy falls back to `User::can()`. Set `false` to keep the Gate and RBAC systems fully independent. |

The fallback only fires for scoped abilities (those containing a `.`). Bare
ability names (`update`, `dashboard.access` with no closure/policy) are **not**
bridged — they resolve to `null` (deny). The setting is read at check time via
`setting('AuthSecurity.gateFallbackToRbac')`.

```php
// With $gateFallbackToRbac = true (default):
service('gate')->allows('users.edit');     // === auth()->user()->can('users.edit')
$routes->get('admin/users', 'Users::index', ['filter' => 'gate:users.edit']);
// behaves like ['filter' => 'permission:users.edit']

// With $gateFallbackToRbac = false:
service('gate')->allows('users.edit');     // false unless a closure/policy is registered
```

### When to disable it

Disable the bridge (`false`) when you want the Gate to be the **sole** source of
truth for ability checks and a missing closure/policy should always deny —
regardless of what RBAC permissions a user holds. This keeps "ability" checks
(`gate:` / `canDo()`) and "permission" checks (`permission:` / `can()`) as two
independent layers, which is useful when abilities encode resource-aware rules
that must never be silently satisfied by a coarse RBAC scope.

---

## Resolvable Repository Services

The transactional persistence of a user's group/permission pivot rows is owned by
`Daycry\Auth\Authorization\GroupPermissionRepository`. It was extracted from the
`Authorizable` trait so the `User` entity no longer opens database transactions
itself — `addGroup()` / `removeGroup()` / `addPermission()` / `removePermission()`
delegate their pivot writes to this repository.

It is registered as an overridable shared service. Resolve (or rebind) it via the
`service()` helper:

```php
service('groupPermissionRepository'); // Daycry\Auth\Authorization\GroupPermissionRepository
```

The token CRUD repositories are likewise resolvable and overridable services —
rebind any of them to swap the underlying storage:

| Service | Class | Owns |
|---------|-------|------|
| `service('groupPermissionRepository')` | `Authorization\GroupPermissionRepository` | RBAC group/permission pivot rows (`saveUserPivot()`) |
| `service('accessTokenRepository')` | `Models\AccessTokenRepository` | Access-token CRUD |
| `service('jwtTokenRepository')` | `Models\JwtTokenRepository` | JWT refresh-token CRUD |
| `service('oauthTokenRepository')` | `Models\OAuthTokenRepository` | OAuth identity CRUD |

```php
// Override the binding in app/Config/Services.php to swap RBAC pivot storage:
public static function groupPermissionRepository(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('groupPermissionRepository');
    }

    return new \App\Authorization\CustomGroupPermissionRepository();
}
```

---

## Authorization in Controllers

### Pattern 1: Early Return

```php
<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class PostController extends BaseController
{
    public function delete(int $id)
    {
        if (! auth()->user()->can('posts.delete')) {
            return redirect()->back()->with('error', 'You are not authorized to delete posts.');
        }

        // Delete the post
        model(\App\Models\PostModel::class)->delete($id);

        return redirect()->to('posts')->with('message', 'Post deleted.');
    }
}
```

### Pattern 2: Exception-Based

Use the Gate's fail-fast `authorize()` — when [`gateFallbackToRbac`](#gate--rbac-bridge)
is enabled (the default), a scoped ability with no registered closure/policy
defers to `User::can()`, so `posts.delete` is resolved against the user's RBAC
permissions and throws `AuthorizationException` on a deny:

```php
use Daycry\Auth\Exceptions\AuthorizationException;

public function delete(int $id)
{
    // Throws AuthorizationException (HTTP 403) if not authorized
    service('gate')->authorize('posts.delete');

    model(\App\Models\PostModel::class)->delete($id);
    return redirect()->to('posts')->with('message', 'Post deleted.');
}
```

Set a global exception handler to catch `AuthorizationException` and redirect to a "403 Forbidden" page:

```php
// app/Config/Exceptions.php or a custom ExceptionHandler
use Daycry\Auth\Exceptions\AuthorizationException;

// In your BaseController or exception handler:
if ($e instanceof AuthorizationException) {
    return redirect()->to('/')->with('error', 'You do not have permission to do that.');
}
```

### Pattern 3: Group + Permission Check Together

```php
public function adminPanel()
{
    $user = auth()->user();

    if (! $user->inGroup('admin') && ! $user->can('admin.panel')) {
        return redirect()->to('/')->with('error', 'Access denied.');
    }

    return view('admin/index');
}
```

---

## Authorization in Views

Show or hide UI elements based on the current user's permissions:

```html
<!-- Show the edit button only to users who can edit posts -->
<?php if (auth()->user()?->can('posts.edit')): ?>
    <a href="<?= site_url('posts/edit/' . $post->id) ?>" class="btn btn-sm btn-secondary">
        Edit
    </a>
<?php endif ?>

<!-- Show the delete button only to admins -->
<?php if (auth()->user()?->inGroup('admin')): ?>
    <button type="button" class="btn btn-sm btn-danger"
            onclick="confirmDelete(<?= $post->id ?>)">
        Delete
    </button>
<?php endif ?>

<!-- Admin navigation link -->
<?php if (auth()->user()?->inGroup('admin', 'moderator')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= site_url('admin') ?>">Admin Panel</a>
    </li>
<?php endif ?>
```

> **Note**: Use `auth()->user()?->can(...)` (null-safe operator) when the user might not be logged in.

---

## Route Filters

Protect entire route groups using filters — no controller code needed:

```php
// app/Config/Routes.php

// Require 'admin' group
$routes->group('admin', ['filter' => 'auth:session,group:admin'], static function ($routes) {
    $routes->get('/', 'Admin\DashboardController::index');
    $routes->get('users', 'Admin\UsersController::index');
});

// Require a specific permission
$routes->group('posts', ['filter' => 'auth:session,permission:posts.create'], static function ($routes) {
    $routes->get('new', 'PostController::new');
    $routes->post('create', 'PostController::create');
});

// Multiple groups (user must be in AT LEAST ONE)
$routes->get('moderation', 'ModerationController::index', ['filter' => 'auth:session,group:admin,moderator']);

// Multiple permissions (user must have ALL)
$routes->post('posts/publish/(:num)', 'PostController::publish/$1',
    ['filter' => 'auth:session,permission:posts.publish,posts.edit']);
```

### Filter Aliases

The `group`, `permission` and `gate` aliases (along with `auth`, `chain`, `rates`,
`force-reset`, `token-scope`, `password-age`, `password-confirm`) are
**auto-registered** by `Daycry\Auth\Config\Registrar::Filters()` — you do not
declare them in `app/Config/Filters.php`. To require an authenticated session,
use the `auth` filter with the `session` argument (`auth:session`); there is no
standalone `session` alias. See the [Filters guide](04-filters.md).

### What Happens on Denial?

When a user fails a group or permission check:
- **`GroupFilter`** redirects to the URL defined in `config('Auth')->redirects['group_denied']`
- **`PermissionFilter`** redirects to `config('Auth')->redirects['permission_denied']`

Configure these in `app/Config/Auth.php`:

```php
public array $redirects = [
    'group_denied'      => '/',   // Redirect when not in required group
    'permission_denied' => '/',   // Redirect when missing required permission
];
```

---

## Permission Cache

In production, repeated permission checks can generate many database queries. Enable the permission cache to store each user's groups and permissions in the CI4 cache:

```php
// app/Config/AuthSecurity.php
public bool $permissionCacheEnabled = true; // default: false
public int  $permissionCacheTTL     = 300;  // Seconds (5 minutes)
```

### Cache Invalidation

The cache is **automatically invalidated** whenever you call:

```php
$user->addGroup('admin');
$user->removeGroup('editor');
$user->addPermission('posts.create');
$user->removePermission('posts.delete');
```

For manual invalidation:

```php
$user->clearPermissionCache();
```

### When to Enable the Cache

| Environment | Recommendation |
|-------------|----------------|
| Development | Disable (easier debugging) |
| Staging     | Enable (realistic performance testing) |
| Production  | Always enable |

---

## Default Group for New Users

Automatically assign all new registrations to a group:

```php
// app/Config/Auth.php
public string $defaultGroup = 'user';
```

During registration, `UserModel::addToDefaultGroup()` is called automatically. The group must exist in the `auth_groups` table — create it via the Admin Panel or a migration.

---

## Admin Panel

Daycry Auth ships with a Bootstrap 5 admin panel for managing groups and permissions without writing any code:

```php
// app/Config/Routes.php
$routes->group('admin/auth', ['filter' => 'auth:session,group:admin', 'namespace' => 'Daycry\Auth\Controllers\Admin'], static function ($routes) {
    $routes->get('/',           'DashboardController::index', ['as' => 'auth-admin']);
    $routes->resource('users',       ['controller' => 'UsersController']);
    $routes->resource('groups',      ['controller' => 'GroupsController']);
    $routes->resource('permissions', ['controller' => 'PermissionsController']);
});
```

From the admin panel you can:
- Create and edit groups
- Assign permissions to groups
- Assign users to groups
- View and revoke individual user permissions

---

## Best Practices

### 1. Use Groups for Roles, Permissions for Actions

```php
// Good: Groups for broad roles
$user->addGroup('editor');

// Good: Permissions for specific capabilities
$user->addPermission('posts.publish');

// Avoid: Over-granular groups
// $user->addGroup('post-editor'); // Use a group + permission instead
```

### 2. Check Permissions, Not Groups, in Business Logic

```php
// Preferred: permission check (flexible)
if ($user->can('posts.delete')) { ... }

// Avoid: group check in business logic (brittle)
if ($user->inGroup('admin')) { ... } // What if a 'moderator' should also delete?
```

### 3. Enable Cache in Production

```php
// app/Config/AuthSecurity.php
public bool $permissionCacheEnabled = ENVIRONMENT === 'production';
```

### 4. Keep Permission Names Consistent

Use `resource.action` format consistently:

```text
users.view    users.create    users.edit    users.delete
posts.view    posts.create    posts.edit    posts.delete    posts.publish
admin.panel   admin.settings  admin.logs
```

---

🔗 **See also**:
- [Filters](04-filters.md) — Route-level authorization filters
- [Controllers](05-controllers.md) — Controller-level authorization patterns
- [Configuration](02-configuration.md) — Permission cache and redirect configuration

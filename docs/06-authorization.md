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
$user->getPermissions();                    // array of permission names

// Shortcuts (throws AuthorizationException on failure)
$user->authorize('posts.delete');

// Multiple groups / permissions
$user->inGroup('admin', 'moderator');       // true if in ANY of them
$user->hasAnyPermission('posts.edit', 'posts.delete'); // true if has ANY
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

```
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

// Check multiple permissions (true if the user has ALL of them)
if ($user->can('posts.create') && $user->can('posts.publish')) {
    // User can create AND publish
}

// Check if user has ANY of several permissions
if ($user->hasAnyPermission('posts.edit', 'posts.delete')) {
    // Can edit or delete
}
```

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

```
User
 ├── Direct permissions: [posts.delete]
 └── Groups:
      ├── editor  → permissions: [posts.create, posts.edit]
      └── premium → permissions: [posts.feature]

Effective permissions: [posts.delete, posts.create, posts.edit, posts.feature]
```

`$user->can('posts.create')` returns `true` because `editor` group has that permission, and the user is in the `editor` group.

### Wildcard Permissions

Use `*` as a wildcard:

```php
// Grant access to all "posts" permissions
$user->addPermission('posts.*');

$user->can('posts.create'); // true
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

```php
use Daycry\Auth\Exceptions\AuthorizationException;

public function delete(int $id)
{
    // Throws AuthorizationException if not authorized
    auth()->user()->authorize('posts.delete');

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
$routes->group('admin', ['filter' => 'session,group:admin'], static function ($routes) {
    $routes->get('/', 'Admin\DashboardController::index');
    $routes->get('users', 'Admin\UsersController::index');
});

// Require a specific permission
$routes->group('posts', ['filter' => 'session,permission:posts.create'], static function ($routes) {
    $routes->get('new', 'PostController::new');
    $routes->post('create', 'PostController::create');
});

// Multiple groups (user must be in AT LEAST ONE)
$routes->get('moderation', 'ModerationController::index', ['filter' => 'session,group:admin,moderator']);

// Multiple permissions (user must have ALL)
$routes->post('posts/publish/(:num)', 'PostController::publish/$1',
    ['filter' => 'session,permission:posts.publish,posts.edit']);
```

### Register Filter Aliases

```php
// app/Config/Filters.php
public array $aliases = [
    'session'    => \Daycry\Auth\Filters\AuthSessionFilter::class,
    'group'      => \Daycry\Auth\Filters\GroupFilter::class,
    'permission' => \Daycry\Auth\Filters\PermissionFilter::class,
];
```

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
// app/Config/Auth.php
public bool $permissionCacheEnabled = true;
public int  $permissionCacheTTL     = 300; // Seconds (5 minutes)
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
$routes->group('admin/auth', ['filter' => 'session,group:admin', 'namespace' => 'Daycry\Auth\Controllers\Admin'], static function ($routes) {
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
// app/Config/Auth.php
public bool $permissionCacheEnabled = ENVIRONMENT === 'production';
```

### 4. Keep Permission Names Consistent

Use `resource.action` format consistently:

```
users.view    users.create    users.edit    users.delete
posts.view    posts.create    posts.edit    posts.delete    posts.publish
admin.panel   admin.settings  admin.logs
```

---

🔗 **See also**:
- [Filters](04-filters.md) — Route-level authorization filters
- [Controllers](05-controllers.md) — Controller-level authorization patterns
- [Configuration](02-configuration.md) — Permission cache and redirect configuration

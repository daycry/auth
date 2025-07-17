# 👥 Authorization System

The authorization system in Daycry Auth provides fine-grained access control for your application.

## 📋 Table of Contents

- [🔑 Permissions](#-permissions)
- [👥 Groups](#-groups)
- [🛡️ Access Control](#️-access-control)
- [🏗️ Implementation](#️-implementation)

## 🔑 Permissions

### Basic Permission Usage

```php
<?php
// Check if user has permission
if (auth()->user()->can('posts.create')) {
    // User can create posts
}

// Add permission to user
auth()->user()->addPermission('posts.edit');

// Remove permission from user
auth()->user()->removePermission('posts.delete');
```

### Permission Types

- **Direct Permissions**: Assigned directly to users
- **Group Permissions**: Inherited from groups
- **Wildcard Permissions**: Using `*` for broader access

## 👥 Groups

### Working with Groups

```php
<?php
// Check if user is in group
if (auth()->user()->inGroup('admin')) {
    // User is admin
}

// Add user to group
auth()->user()->addToGroup('editors');

// Remove user from group
auth()->user()->removeFromGroup('contributors');
```

### Group Hierarchy

Groups can have hierarchical permissions and inheritance patterns.

## 🛡️ Access Control

### In Controllers

```php
<?php
class PostController extends BaseController
{
    public function create()
    {
        if (!auth()->user()->can('posts.create')) {
            throw new AuthorizationException('Not authorized');
        }
        
        // Create post logic
    }
}
```

### In Views

```php
<?php if (auth()->user()->can('posts.edit')): ?>
    <a href="<?= site_url('posts/edit/' . $post->id) ?>">Edit</a>
<?php endif ?>
```

## 🏗️ Implementation

Detailed implementation examples and best practices will be added in future updates.

---

For more information, see the [Authentication Guide](03-authentication.md) and [Filters Documentation](04-filters.md).

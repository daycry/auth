# 🛡️ Security Filters

Filters are the cornerstone of security in Daycry Auth. This complete guide will teach you how to use each available filter.

## 📋 Filter Index

- [🔐 Authentication Filters](#-authentication-filters)
- [👥 Authorization Filters](#-authorization-filters)
- [🔗 Chain Filters](#-chain-filters)
- [📊 Control Filters](#-control-filters)
- [🛠️ Advanced Configuration](#️-advanced-configuration)
- [🎯 Practical Examples](#-practical-examples)

## 🔧 Initial Setup

### 1. Register Filters in `app/Config/Filters.php`

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        // === AUTHENTICATION FILTERS ===
        'session'      => \Daycry\Auth\Filters\AuthSessionFilter::class,
        'tokens'       => \Daycry\Auth\Filters\AuthAccessTokenFilter::class,
        'jwt'          => \Daycry\Auth\Filters\AuthJWTFilter::class,
        'chain'        => \Daycry\Auth\Filters\ChainFilter::class,
        
        // === AUTHORIZATION FILTERS ===
        'group'        => \Daycry\Auth\Filters\GroupFilter::class,
        'permission'   => \Daycry\Auth\Filters\PermissionFilter::class,
        
        // === CONTROL FILTERS ===
        'auth-rates'   => \Daycry\Auth\Filters\AuthRatesFilter::class,
        'force-reset'  => \Daycry\Auth\Filters\ForcePasswordResetFilter::class,
        'auth-request' => \Daycry\Auth\Filters\AuthRequestFilter::class,
    ];

    // Global filters (optional)
    public array $globals = [
        'before' => [
            // 'auth-rates', // Global rate limiting
        ],
        'after' => [],
    ];
}
```

## 🔐 Authentication Filters

### 1. **Session Filter** (`session`)

Verifies that the user is authenticated via session.

#### Basic Usage

```php
// In routes
$routes->group('dashboard', ['filter' => 'session'], function($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('profile', 'Dashboard::profile');
});

// In controller
class Dashboard extends BaseController
{
    protected $filters = ['session'];
    
    public function index()
    {
        // User guaranteed as authenticated
        $user = auth()->user();
        return view('dashboard', ['user' => $user]);
    }
}
```

#### Advanced Configuration

```php
// Apply only to specific methods
protected $filters = [
    'session' => ['except' => ['public_method']]
];

// With additional parameters
$routes->get('admin', 'Admin::index', ['filter' => 'session:admin']);
```

### 2. **Access Token Filter** (`tokens`)

Verifies authentication via access tokens.

#### Usage in APIs

```php
// API Routes
$routes->group('api/v1', ['filter' => 'tokens'], function($routes) {
    $routes->get('users', 'API\Users::index');
    $routes->post('users', 'API\Users::create');
    $routes->resource('posts', ['controller' => 'API\Posts']);
});

// In API controller
class UsersAPI extends ResourceController
{
    protected $filters = ['tokens'];
    
    public function index()
    {
        // Token automatically validated
        $user = auth('access_token')->user();
        
        return $this->respond([
            'user' => $user,
            'data' => $this->model->findAll()
        ]);
    }
}
```

#### Required Headers

```javascript
// In AJAX/API requests
fetch('/api/v1/users', {
    headers: {
        'X-API-KEY': 'your-access-token-here',
        'Content-Type': 'application/json'
    }
});
```

### 3. **JWT Filter** (`jwt`)

Verifies JWT tokens in the Authorization header.

#### Configuration

```php
// API with JWT
$routes->group('api/jwt', ['filter' => 'jwt'], function($routes) {
    $routes->get('profile', 'API\Profile::show');
    $routes->put('profile', 'API\Profile::update');
});
```

#### JWT Headers

```javascript
// Request with JWT
fetch('/api/jwt/profile', {
    headers: {
        'Authorization': 'Bearer your-jwt-token-here',
        'Content-Type': 'application/json'
    }
});
```

### 4. **Chain Filter** (`chain`)

Tries multiple authentication methods in order.

#### Configuration

```php
// In Auth.php
public array $authenticationChain = [
    'session',      // First session
    'access_token', // Then access token
    'jwt',          // Finally JWT
];

// Usage in routes
$routes->group('hybrid', ['filter' => 'chain'], function($routes) {
    $routes->get('data', 'Hybrid::getData'); // Accepts any auth method
});
```

#### Practical Example

```php
class HybridController extends BaseController
{
    protected $filters = ['chain'];
    
    public function getData()
    {
        // Works with session, token or JWT
        $user = auth()->user();
        
        // Detect authentication method used
        $authMethod = 'unknown';
        if (auth('session')->loggedIn()) $authMethod = 'session';
        elseif (auth('access_token')->loggedIn()) $authMethod = 'token';
        elseif (auth('jwt')->loggedIn()) $authMethod = 'jwt';
        
        return $this->respond([
            'user' => $user,
            'auth_method' => $authMethod,
            'data' => 'sensitive data here'
        ]);
    }
}
```

## 👥 Authorization Filters

### 1. **Group Filter** (`group`)

Verifies that the user belongs to one or more groups.

#### Basic Usage

```php
// Single group
$routes->group('admin', ['filter' => 'session,group:admin'], function($routes) {
    $routes->get('/', 'Admin::dashboard');
    $routes->get('users', 'Admin::users');
});

// Multiple groups (OR - any of them)
$routes->get('moderator-panel', 'Moderator::panel', [
    'filter' => 'session,group:admin,moderator'
]);

// In controller
class AdminController extends BaseController
{
    protected $filters = [
        'session',
        'group:admin,super-admin' // Any of these groups
    ];
}
```

#### Hierarchical Groups

```php
// Configure hierarchy in Database Seeder
$groups = [
    'super-admin' => ['permissions' => ['*']],
    'admin'       => ['permissions' => ['admin.*']],
    'moderator'   => ['permissions' => ['content.*']],
    'user'        => ['permissions' => ['user.profile']]
];

// Usage with hierarchy
$routes->group('management', ['filter' => 'session,group:admin'], function($routes) {
    $routes->get('/', 'Management::index');
    
    // Only super-admin
    $routes->group('system', ['filter' => 'group:super-admin'], function($routes) {
        $routes->get('settings', 'Management::systemSettings');
    });
});
```

### 2. **Permission Filter** (`permission`)

Verifies specific granular permissions.

#### Basic Usage

```php
// Specific permission
$routes->get('admin/users/edit/(:num)', 'Admin\Users::edit/$1', [
    'filter' => 'session,permission:users.edit'
]);

// Multiple permissions (AND - must have all)
$routes->delete('admin/users/(:num)', 'Admin\Users::delete/$1', [
    'filter' => 'session,permission:users.delete,users.manage'
]);

// In controller
class UserManagement extends BaseController
{
    protected $filters = [
        'session',
        'permission:users.view' => ['except' => ['index']],
        'permission:users.edit' => ['only' => ['edit', 'update']],
        'permission:users.delete' => ['only' => ['delete']],
    ];
}
```

#### Granular Permission System

```php
// Example permission structure
$permissions = [
    // User management
    'users.view',
    'users.create',
    'users.edit',
    'users.delete',
    'users.manage',
    
    // Content management
    'content.view',
    'content.create',
    'content.edit',
    'content.publish',
    'content.delete',
    
    // System settings
    'system.settings',
    'system.backups',
    'system.logs',
];

// Usage in specific routes
$routes->group('admin/content', ['filter' => 'session'], function($routes) {
    $routes->get('/', 'Content::index', ['filter' => 'permission:content.view']);
    $routes->get('create', 'Content::create', ['filter' => 'permission:content.create']);
    $routes->post('store', 'Content::store', ['filter' => 'permission:content.create']);
    $routes->get('(:num)/edit', 'Content::edit/$1', ['filter' => 'permission:content.edit']);
    $routes->put('(:num)', 'Content::update/$1', ['filter' => 'permission:content.edit']);
    $routes->delete('(:num)', 'Content::destroy/$1', ['filter' => 'permission:content.delete']);
});
```

## 🔗 Chain Filters

### Advanced Chain Configuration

```php
// In Auth.php - order matters
public array $authenticationChain = [
    'session',      // Fastest, for web users
    'access_token', // For external APIs
    'jwt',          // For SPAs and mobile
];

// Custom chain per route
$routes->group('api/mobile', [
    'filter' => 'chain:jwt,access_token' // Only JWT and tokens
], function($routes) {
    $routes->resource('posts');
});
```

### Hybrid API Example

```php
class HybridAPI extends ResourceController
{
    protected $filters = ['chain'];
    
    public function before(RequestInterface $request, $arguments = null)
    {
        // Specific logic based on auth method
        $response = parent::before($request, $arguments);
        
        if (auth('jwt')->loggedIn()) {
            // JWT-specific configuration
            $this->format = 'json';
        } elseif (auth('session')->loggedIn()) {
            // Web session configuration
            $this->format = 'html';
        }
        
        return $response;
    }
}
```

## 📊 Control Filters

### 1. **Auth Rates Filter** (`auth-rates`)

Request rate control per user/IP.

#### Global Configuration

```php
// In Filters.php
public array $globals = [
    'before' => [
        'auth-rates', // Apply to all routes
    ],
];

// In Auth.php
public string $limitMethod = 'USER';     // Per authenticated user
public int $requestLimit = 100;          // 100 requests
public int $timeLimit = HOUR;            // Per hour
```

#### Specific Configuration

```php
// API-specific rate limiting
$routes->group('api', ['filter' => 'auth-rates:50,MINUTE'], function($routes) {
    $routes->resource('users');
});

// In controller with custom rate limiting
class APIController extends ResourceController
{
    protected $filters = [
        'tokens',
        'auth-rates:200,HOUR' // 200 requests per hour
    ];
}
```

### 2. **Force Password Reset Filter** (`force-reset`)

Forces password change when necessary.

```php
// Apply after login
$routes->group('secure', [
    'filter' => 'session,force-reset'
], function($routes) {
    $routes->get('dashboard', 'Dashboard::index');
});

// In database, mark user for reset
auth()->user()->forcePasswordReset();
```

### 3. **Auth Request Filter** (`auth-request`)

Logging and monitoring of authenticated requests.

```php
// Enable request logging
$routes->group('admin', [
    'filter' => 'session,auth-request'
], function($routes) {
    $routes->get('/', 'Admin::index');
});
```

## 🛠️ Advanced Configuration

### Conditional Filters

```php
class ConditionalController extends BaseController
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        
        // Apply filters conditionally
        if ($this->request->isAJAX()) {
            $this->filters['tokens'] = ['only' => ['api_method']];
        } else {
            $this->filters['session'] = ['only' => ['web_method']];
        }
    }
}
```

### Custom Filters

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Custom authentication logic
        if (!auth()->loggedIn()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)
                    ->setJSON(['error' => 'Unauthorized']);
            }
            
            return redirect()->to('/login');
        }
        
        // Additional validations
        $user = auth()->user();
        if (!$user->active) {
            return redirect()->to('/account-suspended');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Logic after response
        if (auth()->loggedIn()) {
            // Update last activity
            auth()->user()->updateLastActive();
        }
    }
}
```

### Filter Combination

```php
// Multiple filters in specific order
$routes->group('secure-api', [
    'filter' => 'auth-rates,chain,permission:api.access'
], function($routes) {
    $routes->resource('sensitive-data');
});

// In controller with multiple filters
class SecureController extends BaseController
{
    protected $filters = [
        'session',                     // Must be authenticated
        'group:admin,moderator',       // Must be admin or moderator
        'permission:admin.access',     // Must have specific permission
        'force-reset',                 // Check if password reset needed
        'auth-rates:50,HOUR',         // Maximum 50 requests per hour
    ];
}
```

## 🎯 Practical Examples

### 1. **Complete Admin Panel**

```php
// Main panel - requires authentication
$routes->group('admin', [
    'namespace' => 'App\Controllers\Admin',
    'filter' => 'session'
], function($routes) {
    
    // Dashboard - basic admin access
    $routes->get('/', 'Dashboard::index', ['filter' => 'group:admin']);
    
    // User management - specific permissions
    $routes->group('users', ['filter' => 'group:admin'], function($routes) {
        $routes->get('/', 'Users::index', ['filter' => 'permission:users.view']);
        $routes->get('create', 'Users::create', ['filter' => 'permission:users.create']);
        $routes->post('/', 'Users::store', ['filter' => 'permission:users.create']);
        $routes->get('(:num)/edit', 'Users::edit/$1', ['filter' => 'permission:users.edit']);
        $routes->put('(:num)', 'Users::update/$1', ['filter' => 'permission:users.edit']);
        $routes->delete('(:num)', 'Users::destroy/$1', ['filter' => 'permission:users.delete']);
    });
    
    // System settings - super-admin only
    $routes->group('system', ['filter' => 'group:super-admin'], function($routes) {
        $routes->get('settings', 'System::settings');
        $routes->post('settings', 'System::updateSettings');
        $routes->get('backups', 'System::backups', ['filter' => 'permission:system.backups']);
    });
});
```

### 2. **RESTful API with Multiple Auth Methods**

```php
// API that accepts tokens, JWT or session
$routes->group('api/v1', [
    'namespace' => 'App\Controllers\API',
    'filter' => 'auth-rates:1000,HOUR' // Global rate limiting
], function($routes) {
    
    // Public endpoints (no auth)
    $routes->get('status', 'Status::index');
    $routes->post('auth/login', 'Auth::login');
    
    // Authenticated endpoints (any method)
    $routes->group('', ['filter' => 'chain'], function($routes) {
        $routes->get('profile', 'Users::profile');
        $routes->put('profile', 'Users::updateProfile');
        
        // Posts - granular permissions
        $routes->get('posts', 'Posts::index', ['filter' => 'permission:posts.view']);
        $routes->post('posts', 'Posts::create', ['filter' => 'permission:posts.create']);
        $routes->put('posts/(:num)', 'Posts::update/$1', ['filter' => 'permission:posts.edit']);
        $routes->delete('posts/(:num)', 'Posts::delete/$1', ['filter' => 'permission:posts.delete']);
    });
    
    // Admin endpoints - admins only with strict rate limiting
    $routes->group('admin', [
        'filter' => 'chain,group:admin,auth-rates:100,HOUR'
    ], function($routes) {
        $routes->get('stats', 'Admin::stats');
        $routes->get('users', 'Admin::users', ['filter' => 'permission:admin.users']);
    });
});
```

### 3. **Application with Different Access Levels**

```php
class MultiLevelController extends BaseController
{
    protected $filters = [
        'session' => ['except' => ['public']],
        'group:subscriber' => ['only' => ['basic_content']],
        'group:premium' => ['only' => ['premium_content']],
        'group:admin' => ['only' => ['admin_content']],
        'permission:content.moderate' => ['only' => ['moderate']],
    ];

    public function public()
    {
        // Public content
        return view('public_content');
    }

    public function basic_content()
    {
        // Only for users with 'subscriber' group or higher
        return view('basic_content');
    }

    public function premium_content()
    {
        // Only for premium users
        return view('premium_content');
    }

    public function admin_content()
    {
        // Only for administrators
        return view('admin_content');
    }

    public function moderate()
    {
        // Only users with specific moderation permission
        return view('moderation_panel');
    }
}
```

## 🚨 Error Handling

### Custom Responses for Filters

```php
// In app/Config/Filters.php
public array $globals = [
    'before' => [
        'auth-rates' => ['except' => ['api/public/*']],
    ],
];

// Customize responses in events
// In app/Config/Events.php
Events::on('auth.fail', function($result) {
    if (service('request')->isAJAX()) {
        return service('response')->setStatusCode(401)
            ->setJSON([
                'error' => 'Authentication failed',
                'message' => $result->reason(),
                'redirect' => site_url('login')
            ]);
    }
});
```

## 📈 Monitoring and Debugging

### Filter Debugging

```php
// In development, enable filter logging
// In .env
CI_ENVIRONMENT = development

// Filters will automatically log their execution
// Check in writable/logs/
```

### Filter Testing

```php
// In tests
class FilterTest extends FeatureTestCase
{
    public function testAdminFilterRequiresAdminGroup()
    {
        $user = fake(UserModel::class);
        $user->addGroup('user'); // Normal group
        
        $result = $this->actingAs($user)
                      ->get('/admin');
                      
        $result->assertRedirect(); // Should redirect
        $result->assertSessionHas('error');
    }

    public function testAPITokenFilter()
    {
        $token = 'valid-token';
        
        $result = $this->withHeaders(['X-API-KEY' => $token])
                      ->get('/api/users');
                      
        $result->assertOK();
    }
}
```

---

🔗 **Next**: [Controllers](05-controllers.md) - Learn to create robust controllers with the new architecture

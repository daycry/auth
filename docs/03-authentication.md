# 🔐 Authentication - Complete Guide

Daycry Auth supports multiple authentication methods. This guide explains how to use each one.

## 📋 Index

- [🖥️ Session Authenticator](#️-session-authenticator)
- [🔑 Access Token Authenticator](#-access-token-authenticator)
- [🏷️ JWT Authenticator](#️-jwt-authenticator)
- [✨ Magic Link Authentication](#-magic-link-authentication)
- [👤 Guest Authenticator](#-guest-authenticator)
- [⚙️ Authenticator Configuration](#️-authenticator-configuration)

## 🖥️ Session Authenticator

**Ideal use**: Traditional web applications with server sessions.

### Basic Configuration

```php
// app/Config/Auth.php
public array $authenticators = [
    'default' => Session::class,
    'session' => Session::class,
];
```

### Usage in Controllers

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;

class AuthController extends BaseAuthController
{
    public function login()
    {
        $credentials = [
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password')
        ];

        $authenticator = auth('session')->getAuthenticator();
        $result = $authenticator->attempt($credentials);

        if ($result->isOK()) {
            return redirect()->to('/dashboard');
        }

        return redirect()->back()->with('error', $result->reason());
    }

    public function logout()
    {
        auth('session')->logout();
        return redirect()->to('/');
    }
}
```

### Helper Functions

```php
// Check if authenticated
if (auth()->loggedIn()) {
    // User authenticated
}

// Get current user
$user = auth()->user();

// Programmatic login
auth()->login($user);

// Logout
auth()->logout();

// Check credentials without login
$result = auth()->check($credentials);
```

### Session Configuration

```php
// app/Config/Auth.php
public array $sessionConfig = [
    'field'              => 'user',           // Session field
    'allowRemembering'   => true,            // Allow "remember me"
    'rememberCookieName' => 'remember',      // Cookie name
    'rememberLength'     => 30 * DAY,        // "Remember me" duration
];
```

## 🔑 Access Token Authenticator

**Ideal use**: APIs, mobile applications, third-party integrations.

### Configuration

```php
// app/Config/Auth.php
public array $authenticators = [
    'tokens' => AccessToken::class,
];

public array $accessTokens = [
    'lifetime'       => YEAR,           // Token duration
    'unusedLifetime' => 20 * MINUTE,    // Unused time before expiry
];
```

### Generate Access Tokens

```php
<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;

class AuthAPIController extends BaseController
{
    public function login()
    {
        $credentials = $this->request->getJSON(true);
        
        $result = auth('tokens')->attempt($credentials);
        
        if (!$result->isOK()) {
            return $this->response->setJSON([
                'error' => $result->reason()
            ])->setStatusCode(401);
        }

        $user = auth('tokens')->user();
        $token = $user->generateAccessToken('api');

        return $this->response->setJSON([
            'token' => $token->raw_token,
            'user' => $user,
            'expires' => $token->expires
        ]);
    }
}
```

### Use Access Tokens

```php
// In request header
Authorization: Bearer your_token_here

// In query string
GET /api/users?token=your_token_here

// In body (POST)
{
    "token": "your_token_here",
    "data": {...}
}
```

### Filters for Access Tokens

```php
// app/Config/Filters.php
public array $filters = [
    'tokens' => [
        'before' => ['api/*']
    ]
];
```

### Token Management

```php
// Generate token with name
$token = $user->generateAccessToken('mobile-app');

// Get all user tokens
$tokens = $user->accessTokens();

// Revoke specific token
$user->revokeAccessToken($token);

// Revoke all tokens
$user->revokeAllAccessTokens();

// Check if token exists
if ($user->hasAccessToken('mobile-app')) {
    // Token exists
}
```

## 🏷️ JWT Authenticator

**Ideal use**: Stateless APIs, microservices, distributed applications.

### Configuration

```php
// app/Config/Auth.php
public array $authenticators = [
    'jwt' => JWT::class,
];

public array $jwtConfig = [
    'adapter'           => DaycryJWTAdapter::class,
    'algorithmUsed'     => 'HS256',
    'secretKey'         => 'your-secret-key-here',
    'issuer'            => 'your-app-name',
    'audience'          => 'your-app-users',
    'timeToLive'        => HOUR * 4,    // 4 hours
    'notBeforeTime'     => 0,           // Immediately valid
    'allowedClockSkew'  => 60,          // 60 seconds tolerance
];
```

### Generate JWT Tokens

```php
<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;

class JWTAuthController extends BaseController
{
    public function login()
    {
        $credentials = $this->request->getJSON(true);
        
        $authenticator = auth('jwt')->getAuthenticator();
        $result = $authenticator->attempt($credentials);
        
        if (!$result->isOK()) {
            return $this->response->setJSON([
                'error' => $result->reason()
            ])->setStatusCode(401);
        }

        // Token is generated automatically
        $token = $authenticator->getAccessToken($result->extraInfo());

        return $this->response->setJSON([
            'access_token' => $token->raw_token,
            'token_type' => 'Bearer',
            'expires_in' => $token->expires->getTimestamp() - time(),
            'user' => auth('jwt')->user()
        ]);
    }

    public function refresh()
    {
        $authenticator = auth('jwt')->getAuthenticator();
        $newToken = $authenticator->refresh();

        return $this->response->setJSON([
            'access_token' => $newToken->raw_token,
            'expires_in' => $newToken->expires->getTimestamp() - time(),
        ]);
    }
}
```

### Use JWT Tokens

```php
// Authorization Header
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

// Verify token in filters
public array $filters = [
    'jwt' => [
        'before' => ['api/*']
    ]
];
```

### Custom Payload

```php
// Add custom claims to JWT
$authenticator = auth('jwt')->getAuthenticator();
$customClaims = [
    'role' => $user->role,
    'permissions' => $user->getPermissions()
];

$token = $authenticator->generateToken($user, $customClaims);
```

## ✨ Magic Link Authentication

**Ideal use**: Passwordless authentication, user onboarding.

### Configuration

```php
// app/Config/Auth.php
public bool $allowMagicLinkLogins = true;
public int $magicLinkLifetime = 3600; // 1 hour

public array $views = [
    'magic-link-login'   => '\Daycry\Auth\Views\magic_link_form',
    'magic-link-message' => '\Daycry\Auth\Views\magic_link_message',
    'magic-link-email'   => '\Daycry\Auth\Views\magic_link_email',
];
```

### Magic Link Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\MagicLinkController as BaseMagicLinkController;

class MagicLinkController extends BaseMagicLinkController
{
    // Already implemented in base controller
    // You can customize if needed
}
```

### Routes

```php
// app/Config/Routes.php
$routes->get('magic-link', 'MagicLinkController::loginView');
$routes->post('magic-link', 'MagicLinkController::loginAction');
$routes->get('auth/magic-link/verify/(:any)', 'MagicLinkController::verify/$1');
```

### Complete Flow

1. **User requests magic link**:
   ```php
   // POST /magic-link
   {
       "email": "user@example.com"
   }
   ```

2. **System sends email with link**:
   ```html
   <a href="https://your-app.com/auth/magic-link/verify/abc123token">
       Access your account
   </a>
   ```

3. **User clicks and authenticates automatically**

### Customize Magic Link Email

```php
// app/Views/magic_link_email.php
<!DOCTYPE html>
<html>
<head>
    <title>Access your account</title>
</head>
<body>
    <h1>Hello!</h1>
    <p>You requested access to your account. Click the following link:</p>
    
    <a href="<?= site_url('auth/magic-link/verify/' . $token) ?>" 
       style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none;">
        Access now
    </a>
    
    <p><strong>This link expires in 1 hour.</strong></p>
    
    <p>If you didn't request this access, ignore this email.</p>
</body>
</html>
```

## 👤 Guest Authenticator

**Ideal use**: Allow limited access to unauthenticated users.

### Configuration

```php
// app/Config/Auth.php
public array $authenticators = [
    'guest' => Guest::class,
];
```

### Usage

```php
// Always allows access but with null user
$user = auth('guest')->user(); // null

// Useful for filters that need to be flexible
public function handle($request, $response, $arguments)
{
    $user = auth('guest')->user();
    
    if ($user === null) {
        // Guest user - limited access
        return $this->handleGuestAccess($request);
    }
    
    // Authenticated user - full access
    return $this->handleAuthenticatedAccess($request);
}
```

## ⚙️ Authenticator Configuration

### Multiple Authenticators

```php
// app/Config/Auth.php
public array $authenticators = [
    'default' => Session::class,
    'session' => Session::class,
    'tokens'  => AccessToken::class,
    'jwt'     => JWT::class,
    'guest'   => Guest::class,
];

// Use different authenticators
$sessionUser = auth('session')->user();
$tokenUser = auth('tokens')->user();
$jwtUser = auth('jwt')->user();
```

### Default Authenticator

```php
// Without specifying uses 'default'
$user = auth()->user();

// Equivalent to:
$user = auth('default')->user();
```

### Environment Configuration

```php
// app/Config/Auth.php
public function __construct()
{
    parent::__construct();
    
    if (ENVIRONMENT === 'production') {
        $this->jwtConfig['secretKey'] = $_ENV['JWT_SECRET'];
        $this->accessTokens['lifetime'] = HOUR * 2; // Shorter tokens in production
    }
}
```

## 🔄 Switching Between Authenticators

### At Runtime

```php
// Switch authenticator dynamically
if ($this->request->hasHeader('Authorization')) {
    // Use JWT for API
    $user = auth('jwt')->user();
} else {
    // Use session for web
    $user = auth('session')->user();
}
```

### In Filters

```php
<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class FlexibleAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Detect authentication type
        if ($request->hasHeader('Authorization')) {
            $authenticator = 'jwt';
        } elseif ($request->hasHeader('X-API-Token')) {
            $authenticator = 'tokens';
        } else {
            $authenticator = 'session';
        }

        if (!auth($authenticator)->loggedIn()) {
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Post-processing if needed
    }
}
```

## 🔐 Security and Best Practices

### 1. JWT Key Rotation

```php
// Implement key rotation
public array $jwtConfig = [
    'secretKey' => [
        'current' => 'current-secret-key',
        'previous' => 'previous-secret-key', // To validate old tokens
    ],
    'keyRotationInterval' => 30 * DAY,
];
```

### 2. Access Token Expiration

```php
// Configure appropriate expiration
public array $accessTokens = [
    'lifetime'       => 8 * HOUR,      // 8-hour tokens
    'unusedLifetime' => 30 * MINUTE,   // Expire if unused for 30 min
];
```

### 3. Rate Limiting

```php
// Limit authentication attempts
public array $filters = [
    'auth-rates' => [
        'before' => ['auth/*']
    ]
];
```

## 🎯 Next Steps

- **[Filters](04-filters.md)**: Protect routes with authentication filters
- **[Controllers](05-controllers.md)**: Implement custom controllers
- **[Authorization](06-authorization.md)**: Add permissions and roles
- **[Configuration](02-configuration.md)**: Customize all options

---

> 💡 **Tip**: You can combine multiple authenticators in the same application. For example, use sessions for web and JWT for API.

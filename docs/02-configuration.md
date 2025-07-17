# ⚙️ Detailed Configuration

This guide covers all configuration options available in Daycry Auth.

## 📁 Configuration File

The main configuration file is `app/Config/Auth.php`, which extends `Daycry\Auth\Config\Auth`.

```php
<?php

namespace Config;

use Daycry\Auth\Config\Auth as BaseAuth;

class Auth extends BaseAuth
{
    // Your customizations go here
}
```

## 🗄️ Database Configuration

### Database Group

```php
/**
 * Custom database group for Auth tables
 */
public ?string $DBGroup = null; // null = use default

// To use a specific group:
public ?string $DBGroup = 'auth_db';
```

### Custom Table Names

```php
/**
 * Customize table names
 */
public array $tables = [
    'users'              => 'users',                    // Users table
    'permissions'        => 'auth_permissions',         // Permissions
    'permissions_users'  => 'auth_permissions_users',   // User permissions
    'identities'         => 'auth_users_identities',    // Identities (passwords, tokens)
    'logins'             => 'auth_logins',              // Login attempts
    'remember_tokens'    => 'auth_remember_tokens',     // Remember me tokens
    'groups'             => 'auth_groups',              // User groups
    'permissions_groups' => 'auth_permissions_groups',  // Group permissions
    'groups_users'       => 'auth_groups_users',        // Users in groups
    'logs'               => 'auth_logs',                // Activity logs
    'apis'               => 'auth_apis',                // Registered APIs
    'controllers'        => 'auth_controllers',         // Controllers
    'endpoints'          => 'auth_endpoints',           // API endpoints
    'attempts'           => 'auth_attempts',            // Failed attempts
    'rates'              => 'auth_rates',               // Rate control
];
```

## 🔐 Authenticator Configuration

### Available Authenticators

```php
/**
 * Available authenticators
 */
public array $authenticators = [
    'access_token' => AccessToken::class,  // Access tokens
    'session'      => Session::class,      // Traditional sessions
    'jwt'          => JWT::class,          // JSON Web Tokens
    'guest'        => Guest::class,        // Guest user
];

/**
 * Default authenticator
 */
public string $defaultAuthenticator = 'session';
```

### JWT Configuration

```php
/**
 * JWT adapter (for JWT authentication)
 */
public string $jwtAdapter = DaycryJWTAdapter::class;
```

### Session Configuration

```php
/**
 * Session authenticator configuration
 */
public array $sessionConfig = [
    'field'              => 'user',           // Session field
    'allowRemembering'   => true,             // Allow "remember me"?
    'rememberCookieName' => 'remember',       // Cookie name
    'rememberLength'     => 30 * DAY,         // Remember me duration
];
```

### Authentication Chain

```php
/**
 * Authenticator chain for 'chain' filter
 */
public array $authenticationChain = [
    'session',      // First try session
    'access_token', // Then access tokens
    'jwt',          // Finally JWT
];
```

## 👥 User Configuration

### User Registration

```php
/**
 * Allow registration of new users?
 */
public bool $allowRegistration = true;

/**
 * Default group for new users
 */
public string $defaultGroup = 'user';
```

### User Provider

```php
/**
 * Class that handles user persistence
 */
public string $userProvider = UserModel::class;
```

### Valid Login Fields

```php
/**
 * Fields that can be used as credentials
 */
public array $validFields = [
    'email',
    // 'username', // Uncomment to allow username login
];
```

## 🔒 Password Configuration

### Minimum Length

```php
/**
 * Minimum password length
 */
public int $minimumPasswordLength = 8;
```

### Password Validators

```php
/**
 * Validators run on every password
 */
public array $passwordValidators = [
    CompositionValidator::class,        // Check composition (upper, lower, numbers)
    NothingPersonalValidator::class,    // Don't use personal info
    DictionaryValidator::class,         // Don't use dictionary words
    // PwnedValidator::class,           // Check against compromised passwords
];
```

### Hash Algorithm

```php
/**
 * Hash algorithm for passwords
 */
public string $hashAlgorithm = PASSWORD_DEFAULT;

// For BCRYPT:
public int $hashCost = 12; // Computational cost (4-31)

// For ARGON2:
public int $hashMemoryCost = 65536; // Memory in KB
public int $hashTimeCost = 4;       // Iterations
public int $hashThreads = 1;        // Threads
```

### Personal Information Similarity

```php
/**
 * Additional fields considered "personal"
 */
public array $personalFields = [
    'firstname', 
    'lastname',
    'phone'
];

/**
 * Maximum allowed similarity percentage (0-100, 0=disabled)
 */
public int $maxSimilarity = 50;
```

## 🔗 Magic Links

```php
/**
 * Allow login via Magic Links?
 */
public bool $allowMagicLinkLogins = true;

/**
 * Magic Link lifetime in seconds
 */
public int $magicLinkLifetime = HOUR;
```

## 🔑 Access Tokens

```php
/**
 * Enable access tokens?
 */
public bool $accessTokenEnabled = false;

/**
 * Unused access token lifetime
 */
public int $unusedAccessTokenLifetime = YEAR;

/**
 * Force API + Auth authentication?
 */
public bool $strictApiAndAuth = false;

/**
 * Headers for each authenticator type
 */
public array $authenticatorHeader = [
    'access_token' => 'X-API-KEY',
    'jwt'          => 'Authorization',
];
```

## 📊 Logging and Monitoring

### Activity Logging

```php
/**
 * Enable activity logs?
 */
public bool $enableLogs = false;

/**
 * Login attempt logging level
 */
public int $recordLoginAttempt = Auth::RECORD_LOGIN_ATTEMPT_ALL;

// Available constants:
// Auth::RECORD_LOGIN_ATTEMPT_NONE    = 0; // Don't record
// Auth::RECORD_LOGIN_ATTEMPT_FAILURE = 1; // Only failures
// Auth::RECORD_LOGIN_ATTEMPT_ALL     = 2; // Everything
```

### Failed Attempts Control

```php
/**
 * Enable blocking for failed attempts?
 */
public bool $enableInvalidAttempts = false;

/**
 * Maximum attempts before blocking
 */
public int $maxAttempts = 10;

/**
 * Block time in seconds
 */
public int $timeBlocked = 3600; // 1 hour
```

### Rate Limiting Control

```php
/**
 * Method for rate control
 */
public string $limitMethod = 'METHOD_NAME';

// Available options:
// 'IP_ADDRESS'  - Limit by IP
// 'USER'        - Limit by user
// 'METHOD_NAME' - Limit by method
// 'ROUTED_URL'  - Limit by URL

/**
 * Request limit
 */
public int $requestLimit = 10;

/**
 * Time window for the limit
 */
public int $timeLimit = MINUTE;
```

## 🎨 Views and URLs

### Custom Views

```php
/**
 * System views
 */
public array $views = [
    'login'                       => '\Daycry\Auth\Views\login',
    'register'                    => '\Daycry\Auth\Views\register',
    'layout'                      => '\Daycry\Auth\Views\layout',
    'action_email_2fa'            => '\Daycry\Auth\Views\email_2fa_show',
    'action_email_2fa_verify'     => '\Daycry\Auth\Views\email_2fa_verify',
    'action_email_2fa_email'      => '\Daycry\Auth\Views\Email\email_2fa_email',
    'action_email_activate_show'  => '\Daycry\Auth\Views\email_activate_show',
    'action_email_activate_email' => '\Daycry\Auth\Views\Email\email_activate_email',
    'magic-link-login'            => '\Daycry\Auth\Views\magic_link_form',
    'magic-link-message'          => '\Daycry\Auth\Views\magic_link_message',
    'magic-link-email'            => '\Daycry\Auth\Views\Email\magic_link_email',
];
```

### Redirect URLs

```php
/**
 * Redirect URLs for different actions
 */
public array $redirects = [
    'register'          => '/',              // After registration
    'login'             => '/',              // After login
    'logout'            => 'login',          // After logout
    'force_reset'       => '/',              // After forced reset
    'permission_denied' => '/',              // When no permissions
    'group_denied'      => '/',              // When not in group
];
```

### System Routes

```php
/**
 * System route definitions
 */
public array $routes = [
    'register' => [
        ['get', 'register', 'RegisterController::registerView', 'register'],
        ['post', 'register', 'RegisterController::registerAction'],
    ],
    'login' => [
        ['get', 'login', 'LoginController::loginView', 'login'],
        ['post', 'login', 'LoginController::loginAction'],
    ],
    'magic-link' => [
        ['get', 'login/magic-link', 'MagicLinkController::loginView', 'magic-link'],
        ['post', 'login/magic-link', 'MagicLinkController::loginAction'],
        ['get', 'login/verify-magic-link', 'MagicLinkController::verify', 'verify-magic-link'],
    ],
    'logout' => [
        ['get', 'logout', 'LoginController::logoutAction', 'logout'],
    ],
    'auth-actions' => [
        ['get', 'auth/a/show', 'ActionController::show', 'auth-action-show'],
        ['post', 'auth/a/handle', 'ActionController::handle', 'auth-action-handle'],
        ['post', 'auth/a/verify', 'ActionController::verify', 'auth-action-verify'],
    ],
];
```

## 🎯 Post-Authentication Actions

```php
/**
 * Actions to execute after login/registration
 */
public array $actions = [
    'register' => null,                    // No action after registration
    'login'    => Email2FA::class,         // Email 2FA after login
];
```

## 📧 Field Validation

### Username Validation

```php
/**
 * Username validation rules
 */
public array $usernameValidationRules = [
    'label' => 'Auth.username',
    'rules' => [
        'required',
        'max_length[30]',
        'min_length[3]',
        'regex_match[/\A[a-zA-Z0-9\.]+\z/]', // Only alphanumeric and dots
    ],
];
```

### Email Validation

```php
/**
 * Email validation rules
 */
public array $emailValidationRules = [
    'label' => 'Auth.email',
    'rules' => [
        'required',
        'max_length[254]',
        'valid_email',
    ],
];
```

## 🤖 Cronjob Configuration

```php
/**
 * Enable automatic API discovery?
 */
public array $namespaceScope = ['\Daycry\Auth\Controllers'];

/**
 * Methods to exclude from discovery
 */
public array $excludeMethods = ['initController', '_remap'];
```

## 💡 Common Configuration Examples

### API Configuration

```php
public bool $accessTokenEnabled = true;
public bool $enableLogs = true;
public string $defaultAuthenticator = 'access_token';
public array $authenticationChain = ['access_token', 'jwt'];
```

### Secure Configuration

```php
public bool $enableInvalidAttempts = true;
public int $maxAttempts = 5;
public int $timeBlocked = 1800; // 30 minutes
public int $minimumPasswordLength = 12;
```

### Development Configuration

```php
public bool $allowRegistration = true;
public bool $enableLogs = true;
public string $defaultGroup = 'developer';
```

## 🔄 Dynamic Configuration Methods

You can override methods for custom logic:

```php
class Auth extends BaseAuth
{
    /**
     * Dynamic redirect URL after login
     */
    public function loginRedirect(): string
    {
        $user = auth()->user();
        
        if ($user->inGroup('admin')) {
            return site_url('admin/dashboard');
        }
        
        return site_url('user/dashboard');
    }

    /**
     * Customize URL based on conditions
     */
    protected function getUrl(string $url): string
    {
        // Your custom logic here
        return parent::getUrl($url);
    }
}
```

## ✅ Configuration Validation

To verify your configuration is correct:

```bash
php spark auth:check-config
```

This command will check:
- Database connectivity
- Table existence
- Authenticator configuration
- Routes and filters

---

🔗 **Next**: [Authentication](03-authentication.md) - Learn to use each authenticator

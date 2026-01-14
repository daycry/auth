# 🌐 OAuth 2.0 & Social Login

Daycry Auth includes support for OAuth 2.0 authentication, allowing users to log in using external providers like Microsoft Azure, Google, GitHub, etc.

## 📦 Installation

To use OAuth features, you must install the required dependencies:

```bash
composer require league/oauth2-client
```

For specific providers, you may need additional packages. For **Microsoft/Azure**:

```bash
composer require thenetworg/oauth2-azure
```

## ⚙️ Configuration

OAuth configuration is now handled directly in `app/Config/Auth.php` inside the `$providers` array.

```php
<?php

namespace Config;

use Daycry\Auth\Config\Auth as BaseAuth;

class Auth extends BaseAuth
{
    // ... other config

    /**
     * --------------------------------------------------------------------
     * OAuth Providers
     * --------------------------------------------------------------------
     *
     * Configuration for external OAuth providers.
     */
    public array $providers = [
        'azure' => [
            'clientId'                => 'YOUR_CLIENT_ID',
            'clientSecret'            => 'YOUR_CLIENT_SECRET',
            'redirectUri'             => 'http://localhost:8080/oauth/azure/callback',
            'tenant'                  => 'common', // Or specific Tenant ID
            'urlAuthorize'            => '', // Will be auto-generated based on tenant if empty
            'urlAccessToken'          => '', // Will be auto-generated based on tenant if empty
            'urlResourceOwnerDetails' => '',
            'scopes'                  => ['openid', 'profile', 'email', 'offline_access'],
        ],
        // Add other providers here...
    ];
}
```

> **Note**: For Azure, if you provide a `tenant` (e.g., `common`, `organizations`, or a generic GUID), the `urlAuthorize` and `urlAccessToken` will be automatically constructed.

## 🛣️ Routing

If you are using `auth()->routes($routes)` in your `app/Config/Routes.php`, the OAuth routes are automatically configured for you.

| Route | Name | Usage |
|-------|------|-------|
| `oauth/login/(:segment)` | `oauth-login` | Initiates the redirect to the provider. |
| `oauth/callback/(:segment)` | `oauth-callback` | Handles the return from the provider. |

If you need to manually configure them:

```php
// app/Config/Routes.php

$routes->group('oauth', ['namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    // Redirect to provider
    $routes->get('login/(:segment)', 'OauthController::redirect/$1', ['as' => 'oauth-login']);
    
    // Callback handling
    $routes->get('callback/(:segment)', 'OauthController::callback/$1', ['as' => 'oauth-callback']);
});
```

## 💻 Usage & Login

1.  **Add Login Button**:
    ```html
    <a href="<?= url_to('oauth-login', 'azure') ?>" class="btn btn-primary">
        Login with Microsoft
    </a>
    ```

2.  **Flow**:
    *   User clicks the link.
    *   Redirected to Provider (Azure).
    *   User authenticates.
    *   Provider redirects back to your callback URL only.
    *   System matches email, creates user if missing, and logs them in.
    *   **Tokens Stored**: The Access Token and Refresh Token (if `offline_access` scope is used) are stored in the database (`auth_users_identities`).

## 🔄 Refresh Tokens

When using providers that support long-lived access via Refresh Tokens (like Azure with `offline_access` scope), you can programmatically refresh the user's access token when it expires.

The `OauthManager` provides a helper for this.

### Example: Checking and Refreshing Token

You might do this in a Filter or before making API calls on behalf of the user.

```php
use Daycry\Auth\Libraries\Oauth\OauthManager;

$user = auth()->user();
$manager = new OauthManager(config('Auth'));
$manager->setProvider('azure');

// Refresh the token for this user
// This will fetch the stored refresh token from DB, request a new one, 
// update the DB with the new tokens and expiration time.
$newToken = $manager->refreshAccessToken($user);

if ($newToken) {
    // Token refreshed successfully
    $accessToken = $newToken->getToken();
    $refreshToken = $newToken->getRefreshToken(); 
    $expires = $newToken->getExpires();
} else {
    // Failed to refresh (token missing, revoked, or expired)
    // You might want to redirect user to login again
}
```

**Stored Data**:
The tokens are stored in the `auth_users_identities` table:
*   `secret`: Social Provider ID.
*   `secret2`: Current Access Token.
*   `extra`: Current Refresh Token.
*   `expires`: Token expiration timestamp.


The system is built on top of [PHP League's OAuth2 Client](https://oauth2-client.leagueofphp.com/).

### Google Example

1.  Install the package:
    ```bash
    composer require league/oauth2-google
    ```

2.  Update `app/Config/Oauth.php`:
    ```php
    public array $providers = [
        // ...
        'google' => [
            'clientId'     => 'GOOGLE_CLIENT_ID',
            'clientSecret' => 'GOOGLE_CLIENT_SECRET',
            'redirectUri'  => 'http://localhost:8080/oauth/google/callback',
        ],
    ];
    ```

3.  Update `Daycry\Auth\Libraries\Oauth\OauthManager::setProvider` (if necessary to instantiate specific provider classes, though `GenericProvider` works for many standard implementations, usually it is better to instantiate the specific provider class).

    *You might need to extend `OauthManager` or use the GenericProvider config if the provider follows standard OIDC.*

    If using a specific library class like `League\OAuth2\Client\Provider\Google`, ensure `OauthManager` instantiates it correctly. The current implementation defaults `azure` to `TheNetworg\OAuth2\Client\Provider\Azure` and others to `GenericProvider`.

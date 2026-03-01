# 🌐 OAuth 2.0 & Social Login

Daycry Auth integrates with [PHP League's OAuth2 Client](https://oauth2-client.leagueofphp.com/) to support social login via external providers. Users can authenticate with **Google**, **GitHub**, **Facebook**, **Microsoft/Azure**, or any standard OAuth2/OIDC provider.

## 📋 Table of Contents

- [How It Works](#how-it-works)
- [Installation](#installation)
- [Configuration](#configuration)
- [Routing](#routing)
- [Adding Login Buttons](#adding-login-buttons)
- [Provider Examples](#provider-examples)
  - [Google](#google)
  - [GitHub](#github)
  - [Facebook](#facebook)
  - [Microsoft / Azure](#microsoft--azure)
  - [Generic OIDC Provider](#generic-oidc-provider)
- [Refresh Tokens](#refresh-tokens)
- [Unlinking a Provider](#unlinking-a-provider)
- [Account Linking Strategy](#account-linking-strategy)

---

## How It Works

```
User clicks "Login with Google"
        ↓
Redirected to Google OAuth consent screen
        ↓
User authorizes → Google redirects back to /oauth/google/callback
        ↓
System retrieves the user's email from Google
        ↓
If email exists → link the OAuth identity and log in
If email is new → create user account, link identity, log in
        ↓
Tokens stored in auth_users_identities for later API calls
```

The OAuth identity is stored in `auth_users_identities` with type `oauth_{provider}` (e.g., `oauth_google`, `oauth_github`). One user can have multiple OAuth providers linked.

---

## Installation

Start with the base package:

```bash
composer require league/oauth2-client
```

Then install provider-specific packages (only the ones you need):

```bash
composer require league/oauth2-google    # Google
composer require league/oauth2-github    # GitHub
composer require league/oauth2-facebook  # Facebook
composer require thenetworg/oauth2-azure  # Microsoft Azure
```

---

## Configuration

Add your providers in `app/Config/AuthOAuth.php`:

```php
<?php

namespace Config;

use Daycry\Auth\Config\AuthOAuth as BaseAuthOAuth;

class AuthOAuth extends BaseAuthOAuth
{
    public array $providers = [

        'google' => [
            'clientId'     => env('OAUTH_GOOGLE_CLIENT_ID'),
            'clientSecret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
            'redirectUri'  => 'https://yourapp.com/oauth/google/callback',
            'scopes'       => ['openid', 'email', 'profile'],
        ],

        'github' => [
            'clientId'     => env('OAUTH_GITHUB_CLIENT_ID'),
            'clientSecret' => env('OAUTH_GITHUB_CLIENT_SECRET'),
            'redirectUri'  => 'https://yourapp.com/oauth/github/callback',
            'scopes'       => ['user:email'],
        ],

        'facebook' => [
            'clientId'     => env('OAUTH_FACEBOOK_APP_ID'),
            'clientSecret' => env('OAUTH_FACEBOOK_APP_SECRET'),
            'redirectUri'  => 'https://yourapp.com/oauth/facebook/callback',
            'graphApiVersion' => 'v12.0',
            'scopes'       => ['email', 'public_profile'],
        ],

        'azure' => [
            'clientId'     => env('OAUTH_AZURE_CLIENT_ID'),
            'clientSecret' => env('OAUTH_AZURE_CLIENT_SECRET'),
            'redirectUri'  => 'https://yourapp.com/oauth/azure/callback',
            'tenant'       => 'common',   // 'common', 'organizations', or a tenant GUID
            'scopes'       => ['openid', 'profile', 'email', 'offline_access'],
        ],

    ];
}
```

> **Security**: Always load credentials from environment variables, never hardcode them.

---

## Routing

### Automatic Routing

If you use `auth()->routes($routes)` in `app/Config/Routes.php`, OAuth routes are registered automatically.

### Manual Routing

```php
// app/Config/Routes.php
$routes->group('oauth', ['namespace' => 'Daycry\Auth\Controllers'], static function ($routes) {
    // Initiates the redirect to the provider
    $routes->get('login/(:segment)',    'OauthController::redirect/$1',  ['as' => 'oauth-login']);
    // Handles the callback from the provider
    $routes->get('callback/(:segment)', 'OauthController::callback/$1', ['as' => 'oauth-callback']);
});
```

| Route | Named Route | Description |
|-------|-------------|-------------|
| `GET /oauth/login/{provider}` | `oauth-login` | Redirects user to provider |
| `GET /oauth/callback/{provider}` | `oauth-callback` | Handles the return from provider |

---

## Adding Login Buttons

```html
<!-- Google -->
<a href="<?= url_to('oauth-login', 'google') ?>" class="btn btn-light border">
    <img src="/assets/img/google-logo.svg" width="20" class="me-2">
    Continue with Google
</a>

<!-- GitHub -->
<a href="<?= url_to('oauth-login', 'github') ?>" class="btn btn-dark">
    <i class="bi bi-github me-2"></i>Continue with GitHub
</a>

<!-- Facebook -->
<a href="<?= url_to('oauth-login', 'facebook') ?>" class="btn btn-primary">
    <i class="bi bi-facebook me-2"></i>Continue with Facebook
</a>

<!-- Microsoft -->
<a href="<?= url_to('oauth-login', 'azure') ?>" class="btn btn-secondary">
    <img src="/assets/img/ms-logo.svg" width="20" class="me-2">
    Continue with Microsoft
</a>
```

---

## Provider Examples

### Google

```bash
composer require league/oauth2-google
```

**Google Cloud Console setup**:
1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create a project → Enable "Google+ API" or "People API"
3. Credentials → Create OAuth 2.0 Client ID (Web application)
4. Add `https://yourapp.com/oauth/google/callback` to Authorized redirect URIs

```php
'google' => [
    'clientId'     => env('OAUTH_GOOGLE_CLIENT_ID'),
    'clientSecret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
    'redirectUri'  => 'https://yourapp.com/oauth/google/callback',
    'scopes'       => ['openid', 'email', 'profile'],
    'hostedDomain' => 'yourcompany.com', // Optional: restrict to one domain
],
```

---

### GitHub

```bash
composer require league/oauth2-github
```

**GitHub setup**:
1. GitHub → Settings → Developer Settings → OAuth Apps → New OAuth App
2. Set Homepage URL and Authorization callback URL

```php
'github' => [
    'clientId'     => env('OAUTH_GITHUB_CLIENT_ID'),
    'clientSecret' => env('OAUTH_GITHUB_CLIENT_SECRET'),
    'redirectUri'  => 'https://yourapp.com/oauth/github/callback',
    'scopes'       => ['user:email'],
],
```

---

### Facebook

```bash
composer require league/oauth2-facebook
```

**Facebook setup**:
1. Go to [developers.facebook.com](https://developers.facebook.com)
2. Create App → Add "Facebook Login" product
3. Set Valid OAuth Redirect URIs

```php
'facebook' => [
    'clientId'        => env('OAUTH_FACEBOOK_APP_ID'),
    'clientSecret'    => env('OAUTH_FACEBOOK_APP_SECRET'),
    'redirectUri'     => 'https://yourapp.com/oauth/facebook/callback',
    'graphApiVersion' => 'v18.0',
    'scopes'          => ['email', 'public_profile'],
],
```

---

### Microsoft / Azure

```bash
composer require thenetworg/oauth2-azure
```

**Azure setup**:
1. Go to [portal.azure.com](https://portal.azure.com) → Azure Active Directory → App registrations
2. New registration → set Redirect URI to your callback URL
3. Add a client secret under Certificates & secrets

```php
'azure' => [
    'clientId'     => env('OAUTH_AZURE_CLIENT_ID'),
    'clientSecret' => env('OAUTH_AZURE_CLIENT_SECRET'),
    'redirectUri'  => 'https://yourapp.com/oauth/azure/callback',
    // 'common'        = personal + work accounts
    // 'organizations' = work accounts only
    // '{tenant-guid}' = specific tenant only
    'tenant'  => 'common',
    'scopes'  => ['openid', 'profile', 'email', 'offline_access'],
],
```

The `urlAuthorize` and `urlAccessToken` are auto-constructed from the `tenant` value.

---

### Generic OIDC Provider

For providers that follow OpenID Connect standards:

```php
'myoidc' => [
    'clientId'                => env('OIDC_CLIENT_ID'),
    'clientSecret'            => env('OIDC_CLIENT_SECRET'),
    'redirectUri'             => 'https://yourapp.com/oauth/myoidc/callback',
    'urlAuthorize'            => 'https://idp.example.com/authorize',
    'urlAccessToken'          => 'https://idp.example.com/token',
    'urlResourceOwnerDetails' => 'https://idp.example.com/userinfo',
    'scopes'                  => ['openid', 'email', 'profile'],
],
```

---

## Refresh Tokens

Some providers (Azure with `offline_access`, Google with `access_type=offline`) issue refresh tokens that let you make API calls on behalf of users without requiring them to re-authenticate.

### Stored Token Data

After authentication, the following is stored in `auth_users_identities`:

| Column | Contains |
|--------|---------|
| `type` | `oauth_{provider}` (e.g., `oauth_google`) |
| `secret` | Provider's user ID |
| `secret2` | Access token |
| `extra` | Refresh token (if provided) |
| `expires` | Access token expiry timestamp |

### Refresh an Access Token

```php
use Daycry\Auth\Libraries\Oauth\OauthManager;

$user    = auth()->user();
$manager = new OauthManager(config('Auth'));
$manager->setProvider('azure');

$newToken = $manager->refreshAccessToken($user);

if ($newToken !== null) {
    $accessToken  = $newToken->getToken();
    $refreshToken = $newToken->getRefreshToken();
    $expires      = $newToken->getExpires();

    // Make an API call with the fresh token
} else {
    // Refresh failed — redirect to login
    return redirect()->route('login');
}
```

### Check Token Expiry Before API Calls

```php
$manager = new OauthManager(config('Auth'));
$manager->setProvider('google');

$token = $manager->getStoredToken($user);

if ($token !== null && $token->hasExpired()) {
    $token = $manager->refreshAccessToken($user);
}

if ($token !== null) {
    // Use $token->getToken() for the Google API
}
```

---

## Unlinking a Provider

Users can disconnect a social login from their account. Daycry Auth ships with `UserSecurityController::unlinkOauth()` for this.

### Route Setup

```php
$routes->post('security/oauth/unlink/(:segment)',
    'Daycry\Auth\Controllers\UserSecurityController::unlinkOauth/$1',
    ['filter' => 'session', 'as' => 'oauth-unlink']);
```

### Unlink Button in a View

```html
<?php foreach ($linkedProviders as $provider): ?>
    <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-2">
        <span><?= esc(ucfirst($provider)) ?></span>
        <?= form_open('security/oauth/unlink/' . esc($provider), ['method' => 'post']) ?>
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Disconnect <?= esc(ucfirst($provider)) ?>?')">
                Disconnect
            </button>
        <?= form_close() ?>
    </div>
<?php endforeach ?>
```

### Safety Check

The unlink logic ensures users always retain at least one way to sign in. It will refuse to unlink the last identity if the user has no password set.

---

## Account Linking Strategy

When a user authenticates via OAuth, the system:

1. **Finds the user by email** — if an account with that email exists, the OAuth identity is linked to it
2. **Creates a new account** — if no matching email is found, a new user is created automatically
3. **Stores the provider identity** — the OAuth provider ID and tokens are saved in `auth_users_identities`

### Handling New Users from OAuth

Listen to the `registered` event to run onboarding logic for users who sign up via OAuth:

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

Events::on('registered', static function (object $user): void {
    // Assign default group
    model(\Daycry\Auth\Models\UserModel::class)->addToDefaultGroup($user);

    // Send welcome email
    service('email')
        ->setTo($user->email)
        ->setSubject('Welcome!')
        ->setMessage(view('emails/welcome', ['user' => $user]))
        ->send();
});
```

---

🔗 **See also**:
- [Authentication](03-authentication.md) — All authentication methods
- [Configuration](02-configuration.md) — Full `$providers` reference
- [Controllers](05-controllers.md) — Custom OAuth controller patterns
- [UserSecurityController](05-controllers.md#usersecuritycontroller) — Unlink and change email

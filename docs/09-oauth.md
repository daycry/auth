# OAuth 2.0 & Social Login

Daycry Auth integrates with [PHP League's OAuth2 Client](https://oauth2-client.leagueofphp.com/) to support social login via external providers. Users can authenticate with **Google**, **GitHub**, **Facebook**, **Microsoft/Azure**, or any standard OAuth2/OIDC provider.

## Table of Contents

- [How It Works](#how-it-works)
- [Architecture](#architecture)
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
- [Stored Token Data](#stored-token-data)
- [Profile Fields](#profile-fields)
  - [Configuring Profile Fields](#configuring-profile-fields)
  - [Profile Resolvers](#profile-resolvers)
  - [Custom Profile Resolver](#custom-profile-resolver)
  - [Reading Stored Profile Data](#reading-stored-profile-data)
- [Scopes Granted](#scopes-granted)
- [Refresh Tokens](#refresh-tokens)
- [OAuth Events](#oauth-events)
- [OAuthTokenRepository](#oauthtokenrepository)
- [IdentityType Helper](#identitytype-helper)
- [Unlinking a Provider](#unlinking-a-provider)
- [Account Linking Strategy](#account-linking-strategy)
- [Testing OAuth](#testing-oauth)

---

## How It Works

```
User clicks "Login with Google"
        |
Redirected to Google OAuth consent screen
        |
User authorizes -> Google redirects back to /oauth/google/callback
        |
System retrieves the user's email from Google
        |
If email exists -> link the OAuth identity and log in
If email is new -> create user account, link identity, log in
        |
Tokens + profile data stored in auth_users_identities
        |
Events fired: oauth-login (always), oauth-profile-fetched (if fields configured)
```

The OAuth identity is stored in `auth_users_identities` with type `oauth_{provider}` (e.g., `oauth_google`, `oauth_github`). One user can have multiple OAuth providers linked.

---

## Architecture

The OAuth subsystem is composed of several focused classes:

| Class | Responsibility |
|-------|---------------|
| `OauthManager` | Orchestrates the OAuth flow: redirect, callback, token refresh |
| `OAuthTokenRepository` | All OAuth identity CRUD (find, create, update, parse extra) |
| `ProfileResolverFactory` | Creates the appropriate profile resolver for a provider |
| `AzureProfileResolver` | Azure-specific: uses Microsoft Graph API for profile fields |
| `GenericProfileResolver` | Default: extracts fields from `toArray()` or a custom endpoint |
| `IdentityType::oauthProvider()` | Builds the `oauth_{name}` type string (replaces manual concatenation) |

`OauthManager` delegates all identity persistence to `OAuthTokenRepository`, following the same pattern as `AccessTokenRepository` and `JwtTokenRepository`.

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

### Provider Configuration Keys

Each provider entry supports these keys:

| Key | Required | Description |
|-----|----------|-------------|
| `clientId` | Yes | OAuth client ID |
| `clientSecret` | Yes | OAuth client secret |
| `redirectUri` | Yes | Callback URL for the provider |
| `scopes` | No | OAuth scopes to request |
| `fields` | No | Extra profile fields to fetch (see [Profile Fields](#profile-fields)) |
| `fieldsEndpoint` | No | Custom API endpoint for profile fields |
| `profileResolver` | No | Custom profile resolver class (see [Custom Profile Resolver](#custom-profile-resolver)) |
| `tenant` | Azure only | Azure AD tenant: `'common'`, `'organizations'`, or a tenant GUID |

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
2. Create a project -> Enable "Google+ API" or "People API"
3. Credentials -> Create OAuth 2.0 Client ID (Web application)
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
1. GitHub -> Settings -> Developer Settings -> OAuth Apps -> New OAuth App
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
2. Create App -> Add "Facebook Login" product
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
1. Go to [portal.azure.com](https://portal.azure.com) -> Azure Active Directory -> App registrations
2. New registration -> set Redirect URI to your callback URL
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
    'scopes'  => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
    // Optional: fetch extra fields from Microsoft Graph
    'fields'  => ['department', 'jobTitle', 'officeLocation', 'mobilePhone'],
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
    'fields'                  => ['role', 'team'],
    'fieldsEndpoint'          => 'https://api.example.com/userinfo',
    // 'profileResolver'      => \App\OAuth\MyCustomResolver::class,
],
```

---

## Stored Token Data

After authentication, the following is stored in `auth_users_identities`:

| Column | Contains |
|--------|---------|
| `type` | `oauth_{provider}` (e.g., `oauth_google`) |
| `secret` | Provider's social user ID |
| `secret2` | Access token |
| `extra` | JSON object (see below) |
| `expires` | Access token expiry timestamp |

### Extra JSON Structure

The `extra` column stores a JSON object with the following fields:

```json
{
    "refresh_token": "rt_abc123...",
    "scopes_granted": ["openid", "profile", "email"],
    "profile": {
        "department": "Engineering",
        "jobTitle": "Senior Developer"
    },
    "profile_fetched_at": "2026-03-20 14:30:00"
}
```

| Field | Present when | Description |
|-------|-------------|-------------|
| `refresh_token` | Provider issues one | OAuth refresh token for offline access |
| `scopes_granted` | Token includes `scope` value | Array of scopes the provider actually granted (RFC 6749 SS3.3) |
| `profile` | `fields` is configured | Extra profile data from the provider |
| `profile_fetched_at` | `profile` is present | Timestamp when the profile data was last fetched |

**Backward compatibility**: Legacy identities that stored the refresh token as a plain string (not JSON) in `extra` are handled transparently. `OAuthTokenRepository::parseExtra()` detects the format and normalises it.

---

## Profile Fields

Daycry Auth can fetch additional profile data from the provider beyond the standard email and name. This is useful for syncing organisational attributes like department, job title, or custom claims.

### Configuring Profile Fields

Add a `fields` array to any provider configuration:

```php
'azure' => [
    'clientId'     => env('OAUTH_AZURE_CLIENT_ID'),
    'clientSecret' => env('OAUTH_AZURE_CLIENT_SECRET'),
    'redirectUri'  => 'https://yourapp.com/oauth/azure/callback',
    'tenant'       => 'your-tenant-guid',
    'scopes'       => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
    // Fetch these fields from Microsoft Graph on login
    'fields'       => ['department', 'jobTitle', 'officeLocation', 'mobilePhone'],
],
```

When `fields` is set, `OauthManager` calls the appropriate profile resolver after the OAuth callback. The resolved data is stored in the `extra` JSON under the `profile` key, along with a `profile_fetched_at` timestamp.

If the profile fetch fails (network error, permission denied, etc.), login still succeeds — the error is logged as a warning.

### Profile Resolvers

Profile resolvers extract field data from the provider. The `ProfileResolverFactory` chooses the resolver in this order:

1. **Config-based**: If `$providerConfig['profileResolver']` is set, that class is used (must implement `ProfileResolverInterface`)
2. **Built-in map**: `azure` -> `AzureProfileResolver`
3. **Fallback**: `GenericProfileResolver`

#### AzureProfileResolver

For Azure, the resolver calls the Microsoft Graph API (`https://graph.microsoft.com/v1.0/me`) to fetch the requested fields. Requires the `User.Read` scope.

#### GenericProfileResolver

The generic resolver works for any provider. It tries two strategies in order:

1. **Custom endpoint**: If `fieldsEndpoint` is configured, it fetches data from that URL using the access token and filters the response to only the requested fields.
2. **Resource owner data**: If no endpoint is configured (or it fails), it uses `$resourceOwner->toArray()` and filters the fields.

### Custom Profile Resolver

To implement a custom resolver for a specific provider:

```php
<?php

namespace App\OAuth;

use Daycry\Auth\Libraries\Oauth\ProfileResolver\ProfileResolverInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

class MyCustomResolver implements ProfileResolverInterface
{
    public function fetchFields(
        AbstractProvider $provider,
        AccessTokenInterface $token,
        ResourceOwnerInterface $resourceOwner,
        array $fields,
        array $config = [],
    ): array {
        // Custom logic to fetch profile fields
        $request  = $provider->getAuthenticatedRequest('GET', 'https://api.myprovider.com/user/profile', $token);
        $response = $provider->getParsedResponse($request);

        if (! is_array($response)) {
            return [];
        }

        // Return only the requested fields
        return array_intersect_key($response, array_flip($fields));
    }
}
```

Register it in the provider config:

```php
'my_provider' => [
    'clientId'        => env('MY_PROVIDER_CLIENT_ID'),
    'clientSecret'    => env('MY_PROVIDER_CLIENT_SECRET'),
    'redirectUri'     => 'https://yourapp.com/oauth/my_provider/callback',
    'fields'          => ['role', 'team', 'avatar_url'],
    'profileResolver' => \App\OAuth\MyCustomResolver::class,
],
```

If the class does not implement `ProfileResolverInterface`, a `LogicException` is thrown at runtime.

### Reading Stored Profile Data

```php
use Daycry\Auth\Libraries\Oauth\OauthManager;

$user    = auth()->user();
$manager = new OauthManager(config('AuthOAuth'));
$manager->setProvider('azure');

$profile = $manager->getProfileData($user);
// Returns: ['department' => 'Engineering', 'jobTitle' => 'Senior Dev', ...]

// Or use the repository directly:
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;

$repo    = new OAuthTokenRepository(model(UserIdentityModel::class));
$profile = $repo->getProfileData((int) $user->id, 'azure');
```

---

## Scopes Granted

When the OAuth provider returns the granted scopes in the token response (as per RFC 6749 SS3.3), Daycry Auth stores them in the `extra` JSON as `scopes_granted`:

```php
// After login, check what scopes were actually granted
$repo     = new OAuthTokenRepository(model(UserIdentityModel::class));
$identity = $repo->findByUserAndProvider((int) $user->id, 'azure');
$extra    = $repo->parseExtra($identity->extra);

$scopes = $extra['scopes_granted'] ?? [];
// ['openid', 'profile', 'email', 'User.Read']
```

This is useful when the provider may grant fewer scopes than requested (e.g., the user declined a specific permission).

Scopes are also updated when a token is refreshed via `refreshAccessToken()`.

---

## Refresh Tokens

Some providers (Azure with `offline_access`, Google with `access_type=offline`) issue refresh tokens that let you make API calls on behalf of users without requiring them to re-authenticate.

### Refresh an Access Token

```php
use Daycry\Auth\Libraries\Oauth\OauthManager;

$user    = auth()->user();
$manager = new OauthManager(config('AuthOAuth'));
$manager->setProvider('azure');

$newToken = $manager->refreshAccessToken($user);

if ($newToken !== null) {
    $accessToken  = $newToken->getToken();
    $refreshToken = $newToken->getRefreshToken();
    $expires      = $newToken->getExpires();

    // Make an API call with the fresh token
} else {
    // Refresh failed (no identity, no refresh token, or provider error)
    return redirect()->route('login');
}
```

When a token is refreshed:
- The new access token replaces the old one in `secret2`
- If the provider rotates the refresh token, the new one is stored
- If the refreshed token includes scopes, `scopes_granted` is updated
- The existing `profile` and `profile_fetched_at` are preserved (no re-fetch on refresh)

### Error Handling

`refreshAccessToken()` returns `null` in these cases:
- No OAuth identity found for the user/provider
- The identity has no `extra` data or no `refresh_token` in the extra
- The provider rejects the refresh (e.g., token revoked, expired)

The method catches `IdentityProviderException` internally and returns `null` rather than throwing.

---

## OAuth Events

`OauthManager::handleCallback()` fires two events after a successful OAuth login:

| Event | When | Arguments |
|-------|------|-----------|
| `oauth-login` | Always, after login | `User $user`, `string $providerName` |
| `oauth-profile-fetched` | When profile fields were resolved | `User $user`, `string $providerName`, `array $profileData` |

### Listening to OAuth Events

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

// Log all OAuth logins
Events::on('oauth-login', static function (object $user, string $provider): void {
    log_message('info', "OAuth login via {$provider} for user {$user->email}");
});

// Sync profile data to local tables
Events::on('oauth-profile-fetched', static function (object $user, string $provider, array $profileData): void {
    if (isset($profileData['department'])) {
        // Update user's department in a custom table
        db_connect()->table('user_profiles')->upsert([
            'user_id'    => $user->id,
            'department' => $profileData['department'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['user_id']);
    }
});

// Alert on first-time OAuth logins
Events::on('oauth-login', static function (object $user, string $provider): void {
    $identityCount = model(\Daycry\Auth\Models\UserIdentityModel::class)
        ->where('user_id', $user->id)
        ->like('type', 'oauth_', 'after')
        ->countAllResults();

    if ($identityCount === 1) {
        // First OAuth link — send notification
        log_message('info', "New OAuth account linked: {$provider} for {$user->email}");
    }
});
```

---

## OAuthTokenRepository

`OAuthTokenRepository` encapsulates all OAuth identity CRUD, following the same pattern as `AccessTokenRepository` and `JwtTokenRepository`. It wraps `UserIdentityModel` and uses `IdentityType::oauthProvider()` for type strings.

### Available Methods

| Method | Description |
|--------|-------------|
| `findByUserAndProvider(int $userId, string $provider)` | Find the OAuth identity for a user/provider pair |
| `findByProviderAndSocialId(string $provider, string $socialId)` | Find by provider type and social ID |
| `createOAuthIdentity(int $userId, string $provider, array $data)` | Insert a new OAuth identity row |
| `updateOAuthIdentity(UserIdentity $identity)` | Update an existing identity (token refresh, re-login) |
| `getProfileData(int $userId, string $provider)` | Get the stored profile data from the `extra` JSON |
| `parseExtra(?string $extra)` | Parse the `extra` column (handles JSON and legacy plain-string format) |

### Direct Usage

```php
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;

$repo = new OAuthTokenRepository(model(UserIdentityModel::class));

// Find an OAuth identity
$identity = $repo->findByUserAndProvider((int) $user->id, 'google');

if ($identity !== null) {
    $extra = $repo->parseExtra($identity->extra);
    $accessToken  = $identity->secret2;
    $refreshToken = $extra['refresh_token'] ?? null;
    $scopes       = $extra['scopes_granted'] ?? [];
    $profile      = $extra['profile'] ?? [];
}

// Get just the profile data
$profile = $repo->getProfileData((int) $user->id, 'azure');
// ['department' => 'Engineering', 'jobTitle' => 'Senior Dev']
```

`OauthManager` uses the repository internally via a lazy-initialised getter. You generally don't need to use it directly unless building custom OAuth integrations.

---

## IdentityType Helper

OAuth identity types are dynamic (`oauth_google`, `oauth_github`, etc.) because the set of providers is user-defined. Instead of concatenating strings manually, use the static helper:

```php
use Daycry\Auth\Enums\IdentityType;

// Instead of: 'oauth_' . $providerName
$type = IdentityType::oauthProvider('google');  // 'oauth_google'
$type = IdentityType::oauthProvider('azure');   // 'oauth_azure'
```

This centralises the `oauth_` prefix convention and makes OAuth type strings grep-able across the codebase.

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

1. **Looks up by social ID** -- if an OAuth identity with the same provider and social ID already exists, the tokens and profile are updated (re-login)
2. **Finds the user by email** -- if no OAuth identity exists but an account with that email does, the OAuth identity is linked to it
3. **Creates a new account** -- if no matching email is found, a new user is created automatically and assigned to the default group
4. **Stores the provider identity** -- the OAuth provider ID, tokens, scopes, and profile data are saved in `auth_users_identities`

### Handling New Users from OAuth

Listen to the `oauth-login` event to run onboarding logic for users who sign up via OAuth:

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

Events::on('oauth-login', static function (object $user, string $provider): void {
    // Check if this is a brand new user (no other identities)
    // The default group is assigned automatically during OAuth registration.
});
```

---

## Testing OAuth

### Mocking the Provider

`OauthManager::setProviderInstance()` accepts a mock provider for testing:

```php
use Daycry\Auth\Libraries\Oauth\OauthManager;
use Daycry\Auth\Config\AuthOAuth;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;

$provider    = Mockery::mock(AbstractProvider::class);
$accessToken = new AccessToken([
    'access_token'  => 'test_token',
    'refresh_token' => 'refresh_abc',
    'scope'         => 'openid profile email',
]);

$provider->shouldReceive('getAccessToken')
    ->with('authorization_code', ['code' => 'auth_code'])
    ->andReturn($accessToken);

$resourceOwner = Mockery::mock(GenericResourceOwner::class);
$resourceOwner->shouldReceive('getId')->andReturn('social_123');
$resourceOwner->shouldReceive('toArray')->andReturn([
    'email' => 'test@example.com',
    'name'  => 'Test User',
    'role'  => 'admin',
]);

$provider->shouldReceive('getResourceOwner')
    ->with($accessToken)
    ->andReturn($resourceOwner);

$manager = new OauthManager(new AuthOAuth());
$manager->setProviderInstance($provider, 'generic');

session()->set('oauth2state', 'valid_state');
$user = $manager->handleCallback('auth_code', 'valid_state');
```

### Testing Events

```php
use CodeIgniter\Events\Events;

$triggered = false;
Events::on('oauth-login', static function ($user, $providerName) use (&$triggered): void {
    $triggered = true;
});

// ... perform OAuth callback ...

$this->assertTrue($triggered);
```

### Testing the Repository

```php
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Enums\IdentityType;

$repo = new OAuthTokenRepository(model(UserIdentityModel::class));

// Test parseExtra with JSON
$result = $repo->parseExtra('{"refresh_token": "rt", "profile": {"a": 1}}');
$this->assertSame('rt', $result['refresh_token']);

// Test parseExtra with legacy string
$result = $repo->parseExtra('plain_token_string');
$this->assertSame(['refresh_token' => 'plain_token_string'], $result);

// Test parseExtra with null/empty
$this->assertSame([], $repo->parseExtra(null));
$this->assertSame([], $repo->parseExtra(''));
```

### Testing the ProfileResolverFactory

```php
use Daycry\Auth\Libraries\Oauth\ProfileResolver\ProfileResolverFactory;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\AzureProfileResolver;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\GenericProfileResolver;

// Built-in map
$this->assertInstanceOf(AzureProfileResolver::class, ProfileResolverFactory::create('azure'));

// Fallback
$this->assertInstanceOf(GenericProfileResolver::class, ProfileResolverFactory::create('unknown'));

// Config-based override
$resolver = ProfileResolverFactory::create('azure', [
    'profileResolver' => GenericProfileResolver::class,
]);
$this->assertInstanceOf(GenericProfileResolver::class, $resolver);

// Invalid resolver throws LogicException
$this->expectException(\CodeIgniter\Exceptions\LogicException::class);
ProfileResolverFactory::create('test', ['profileResolver' => \stdClass::class]);
```

---

See also:
- [Authentication](03-authentication.md) -- All authentication methods
- [Configuration](02-configuration.md) -- Full `$providers` reference
- [Controllers](05-controllers.md) -- Custom OAuth controller patterns
- [UserSecurityController](05-controllers.md#usersecuritycontroller) -- Unlink and change email
- [Logging & Monitoring](07-logging.md) -- OAuth events and logging
- [Testing](08-testing.md) -- Complete testing guide

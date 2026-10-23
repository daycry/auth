# Extending the Library

Every extension point below works from your application. You don't fork the package or copy its classes. In order of preference:

1. **Configuration**: swap a class (`Auth::$authenticators`, `Auth::$actions`, `Auth::$userProvider`, `Auth::$views`, `AuthSecurity::$passwordValidators`, `AuthOAuth::$providers[...]['profileResolver']`).
2. **Service bindings**: replace a collaborator for the whole library.
3. **Events**: react to what happened without changing it.
4. **Subclassing**: override a protected hook in a controller, filter or authenticator.

---

## Service Bindings

These services are shared, and the library resolves them through `service()`. Override one in your `app/Config/Services.php` and every caller picks up your implementation:

| Service | Default class | Typical override |
|---------|---------------|------------------|
| `auditLogger` | `Services\AuditLogger` | Ship audit events to a SIEM or a queue, enrich metadata |
| `tokenEmailSender` | `Libraries\TokenEmailSender` | Queue magic-link, reset and e-mail-change mails, or use another transport |
| `securityNotifier` | `Libraries\SecurityNotifier` | Customise security notices (password changed, suspicious login, ...) |
| `passwordChangeRecorder` | `Services\PasswordChangeRecorder` | Extra side effects after any password change |
| `userAnonymizer` | `Services\UserAnonymizer` | Also scrub app-owned personal data on GDPR deletion |
| `accessTokenRepository` | `Models\AccessTokenRepository` | Token storage or lookup (used by the `tokens` authenticator) |
| `jwtTokenRepository` | `Models\JwtTokenRepository` | Refresh-token storage |
| `oauthTokenRepository` | `Models\OAuthTokenRepository` | OAuth identity storage (used by `OauthManager`) |
| `groupPermissionRepository` | `Authorization\GroupPermissionRepository` | RBAC pivot persistence |
| `webAuthnManager`, `webAuthnCredentialRepository`, ... | see [WebAuthn](15-webauthn.md) | Passkey ceremonies and storage |

```php
// app/Config/Services.php
namespace Config;

use App\Auth\QueuedTokenEmailSender;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function tokenEmailSender(bool $getShared = true): QueuedTokenEmailSender
    {
        if ($getShared) {
            return static::getSharedInstance('tokenEmailSender');
        }

        return new QueuedTokenEmailSender();
    }
}
```

Your class should extend the default one, so the method signatures the library calls stay the same.

---

## A Custom User Class

Set `$returnType` on your user model and point `Auth::$userProvider` at it. Every authenticator and login flow then resolves users through that provider and returns your class: session, access-token, JWT (including refresh), basic-auth, magic link, OAuth and passkeys. That includes the hand-built hydration of e-mail login (`findByCredentials()`) and of the access-token lookup. Build an entity of the right class yourself with `UserModel::createUserEntity()`.

```php
class User extends \Daycry\Auth\Entities\User
{
    public function displayName(): string
    {
        return $this->username ?? $this->email;
    }
}

class UserModel extends \Daycry\Auth\Models\UserModel
{
    protected $returnType = User::class;
}
```

---

## Events

Every event, with its arguments, is listed in [Logging → Available Events](07-logging.md#available-events). The ones added for extensions are:

| Event | Arguments |
|-------|-----------|
| `user-banned` / `user-unbanned` | `User $user` (+ `?string $message` for `user-banned`) |
| `user-locked` | `User $user`, `string $lockedUntil` |
| `two-factor-verified` / `two-factor-failed` | `User $user`, `string $type` (`totp`, `webauthn`) |
| `access-token-created` | `User $user`, `AccessToken $token` (a copy without `raw_token`) |
| `access-token-revoked` | `User $user`, `?AccessToken $token` (null when all tokens were revoked) |

---

## Protected Hooks

Subclass the class, override the hook, then register your subclass: in `Auth::$authenticators`, the filter aliases, or your routes.

| Class | Hooks |
|-------|-------|
| `Authenticators\Session` | `createLockoutManager()`, `createDeviceSessionRecorder()`, `createActionCoordinator()`, `beginSession()`, `issueRememberMeToken()`, `maybeFireSuspiciousLogin()`, `maybeRecheckPwnedOnLogin()` |
| `Authenticators\StatelessAuthenticator` (`AccessToken`, `JWT`) | `getTokenFromRequest()`, `resolveTokenCredentials()`, `noTokenResult()`, `getLogCredentials()`; `AccessToken::tokenRepository()` |
| `Actions\AbstractAction` (custom 2FA / activation actions) | `verifySecondFactor()`, `insertPendingMarker()`, `sendActionEmail()`, `requirePendingUser()` |
| `Libraries\Oauth\OauthManager` | `processUser()`, `registerUser()`, `linkProviderToUser()`, `buildIdentityExtra()`, `refreshIdentity()`, `createIdentity()`, `fetchProfileFields()`, `getRepository()`; `$providerMap` |
| `Controllers\PasswordResetController` | `getResetValidationRules()`, `resetFormUrl()` |
| `Controllers\MagicLinkController` | `magicLinkDisabled()`, `failLinkVerification()`, `completeMagicLogin()` |
| `Controllers\WebAuthnController` | `enabled()`, `manager()`, `error()`, `withToken()`, `credentialJson()` |
| `Controllers\Admin\*Controller` | `findGroupOr404()`, `findPermOr404()`, `findUserOr404()` |
| `Filters\AuthFilter` | `determineAuthenticator()`, `handleSessionAuthentication()`, `handleTokenAuthentication()`, `validateAccessToken()` |
| `Filters\AbstractAuthFilter` (`group`, `permission`, `gate`, `token-scope`) | `isAuthorized()`, `redirectToDeniedUrl()`, `handleUnauthenticated()` |
| `Filters\RatesFilter`, `BasicAuthFilter`, `PasswordConfirmFilter`, `TokenScopeFilter` | `applyArguments()` / `parsePeriod()` / `buildLimitedUri()`; `extractCredentials()` / `resolveUser()` / `challenge()`; `confirmedRecently()`; `resolveCurrentAccessToken()` |
| `Commands\BaseCommand` | `resolveTargetUser()` (`-e` / `-i` options) |

!!! example "Accept a passkey as proof of presence"
    ```php
    class PasskeyAwarePasswordConfirmFilter extends \Daycry\Auth\Filters\PasswordConfirmFilter
    {
        protected function confirmedRecently(int $lifetime): bool
        {
            return parent::confirmedRecently($lifetime)
                || (time() - (int) session('passkey_verified_at')) <= $lifetime;
        }
    }
    ```

---

## Custom Post-Login Actions

Extend `Authentication\Actions\AbstractAction`, set `$type` to an `IdentityType` value, and register it in `Auth::$actions['login']` or `['register']`. For a second factor, `verify()` is usually one call:

```php
public function verify(IncomingRequest $request)
{
    $code = (string) $request->getPost('token');

    return $this->verifySecondFactor(
        fn (User $user): bool => $this->sms->check($user, $code),
        'action_sms_verify',      // Auth::$views key of the form
        'App.invalidSmsCode',     // lang key flashed on a wrong code
    );
}
```

The `register` action requires e-mail activation when it is `EmailActivator` or a subclass of it. A custom action whose class name contains `Activator` also counts, for backward compatibility.

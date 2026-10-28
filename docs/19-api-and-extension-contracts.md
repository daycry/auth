# JSON forms and extension contracts

## JSON authentication

The existing login, registration, magic-link/code, password-reset, forced-reset
and post-authentication action routes accept form bodies or JSON objects. Send
`Accept: application/json` to receive JSON for a form request; a JSON request
body also selects JSON. Ordinary browser forms keep their redirects and flash
messages. The machine-readable contract is [OpenAPI](openapi.json).

Session authentication still uses the application cookie. These endpoints do
not issue bearer tokens; use the JWT endpoints for that workflow. Keep the
application CSRF filter enabled and send its configured token/header. JSON
responses include `token: {name, hash}` so the client can use the current CSRF
hash on its next request. Authentication results have `Cache-Control: no-store`.

```json
{
  "status": "success",
  "authenticated": true,
  "redirect": "https://example.com/",
  "token": {"name": "csrf_test_name", "hash": "..."}
}
```

| Response | Meaning |
|---|---|
| `200` | Successful login, logout, reset, or generic email-request result |
| `201` | Account registered and login completed |
| `202` | Post-authentication action required; `authenticated` is **false** |
| `400` | Nonempty JSON is malformed or is not an object |
| `401` | Invalid credentials/token or missing required authenticated session |
| `403` | Feature disabled, MFA requirement, or CSRF refusal |
| `422` | Validation failure (`errors` by field) or rejected second factor |
| `429` | Rate limit reached |

An empty JSON body is treated as empty input and reaches the endpoint's normal
validation. JSON arrays and scalar bodies are rejected before a mutation.
Password-reset and passwordless-email requests return the same generic result
for existing and unknown email addresses, including delivery failure.

A `202` result includes `status: "action_required"` and the action URL in
`redirect`. The `auth/a/show`, `auth/a/handle`, and `auth/a/verify` routes also
support JSON. Complete the configured action (email, TOTP, or passkey); only a
successful verification returns `authenticated: true`. A bad proof returns
`422` and leaves the login pending. A mandatory-MFA policy can also return
`202` with `mfa_enrollment_required` or `mfa_verification_required`, even when
no login action is configured. Keep the same session cookie throughout.

Applications extending the form controllers should use `ResponseInterface`
when their override supports both JSON and redirects. `authRequestData()` and
`authInput()` read only the request body, and `authJsonResponse()` adds the
current CSRF token. Existing HTML `handleSuccess`/`handleError` override hooks
retain their parameter lists.

## Replace email delivery without changing authentication

The default `emailDelivery` service implements `EmailDeliveryInterface` and
sends immediately through CodeIgniter's configured SMTP, sendmail, or `mail`
transport. Runtime `Email.*` settings remain supported. No queue or job package
is required. Token generation, templates, account changes and notifications do
not depend on a particular delivery mechanism.

```php
namespace App\Mail;

use Daycry\Auth\Interfaces\EmailDeliveryInterface;

final class ApplicationDelivery implements EmailDeliveryInterface
{
    public function send(string $to, string $subject, string $html): bool
    {
        // Call your application's mail gateway here.
        return $this->gateway()->deliver($to, $subject, $html);
    }

    // Implement gateway() with your application's dependency container.
}
```

Register it in the application's `Config\Services`, using the same service
name. An independent implementation needs no inheritance from a package class:

```php
public static function emailDelivery(bool $getShared = true): \Daycry\Auth\Interfaces\EmailDeliveryInterface
{
    if ($getShared) {
        return static::getSharedInstance('emailDelivery');
    }

    return new \App\Mail\ApplicationDelivery();
}
```

The delivery contract receives the destination, resolved subject, and rendered
HTML. Return `true` once the transport accepts it, or `false` on failure. A
transport may throw: token-email callers receive the established
`CodeIgniter\Exceptions\RuntimeException` failure contract; security notices
log delivery failure and leave the account operation successful. Do not log
message bodies or confirmation URLs in custom delivery code.

## Core contracts

The following existing service bindings return interfaces. The bundled classes
implement them and remain the defaults; an application can provide its own
implementation or subclass the default when it only needs a small override.

| Service | Interface in `Daycry\Auth\Interfaces` |
|---|---|
| `emailDelivery` | `EmailDeliveryInterface` |
| `tokenEmailSender` | `TokenEmailSenderInterface` |
| `securityNotifier` | `SecurityNotifierInterface` |
| `auditLogger` | `AuditLoggerInterface` |
| `passwordChangeRecorder` | `PasswordChangeRecorderInterface` |
| `userAnonymizer` | `UserAnonymizerInterface` |
| `userDataExporter` | `UserDataExporterInterface` |
| `accessTokenRepository` | `AccessTokenRepositoryInterface` |
| `jwtTokenRepository` | `JwtTokenRepositoryInterface` |
| `oauthTokenRepository` | `OAuthTokenRepositoryInterface` |
| `groupPermissionRepository` | `GroupPermissionRepositoryInterface` |
| `webAuthnCredentialRepository` | `WebAuthnCredentialRepositoryInterface` |
| `webAuthnManager` | `WebAuthnManagerInterface` |
| `mfaPolicy` | `MfaPolicyInterface` |

Repository implementations must preserve ownership, expiry and revocation
checks. JWT rotation must atomically consume the old token and handle reuse;
an interface is an extension boundary, not permission to weaken those rules.
Replacing the WebAuthn repository also replaces the enrollment checks and
credential list used by the User entity. Existing `UserProviderInterface`,
`AuthenticatorInterface`, `PasswordValidatorInterface`, `ActionInterface`, and
`JWTAdapterInterface` remain available. See [Extending](16-extending.md) for
events, custom users and the service registration pattern.

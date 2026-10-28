# Completing your authentication setup

This update retains CodeIgniter Email delivery, including your configured SMTP
transport. No queue, worker, or new mail provider is required.

## Replace email delivery

Implement `Daycry\Auth\Interfaces\EmailDeliveryInterface` in your application:

```php
namespace App\Libraries;

use Daycry\Auth\Interfaces\EmailDeliveryInterface;

final class MailDelivery implements EmailDeliveryInterface
{
    public function send(string $to, string $subject, string $html): bool
    {
        // Call your provider here. Return false when delivery fails.
        return service('myMailProvider')->send($to, $subject, $html);
    }
}
```

Override the binding in `app/Config/Services.php`:

```php
public static function emailDelivery(bool $getShared = true): \Daycry\Auth\Interfaces\EmailDeliveryInterface
{
    if ($getShared) {
        return static::getSharedInstance('emailDelivery');
    }

    return new \App\Libraries\MailDelivery();
}
```

This replaces delivery for token emails, account notices, and email second
factors. Token creation, expiry, templates, and account security checks remain
in the library. Override `tokenEmailSender` or `securityNotifier` when you also
want to replace those workflows. Their service return types are interfaces;
your class does not need to inherit from the default implementation.

The same pattern applies to audit logging, password-change bookkeeping,
anonymization, account export, MFA policy, token repositories, group persistence,
and WebAuthn orchestration and credential persistence. See
[extension contracts](19-api-and-extension-contracts.md).

## Routes and compatibility

`auth()->routes($routes)` now registers authenticated account-security and
password-confirmation routes. Applications with their own route groups can
retain them by excluding `security` and `password-confirm`:

```php
auth()->routes($routes, ['except' => ['security', 'password-confirm']]);
```

Generated authentication routes use `rates:auth` by default. Use `Auth::$routeFilters`
to customize a group or explicitly disable its throttle with `false`. Required
authentication on the generated account routes remains in place when a throttle
is disabled. Keep the application's CSRF filter enabled for session-based POST
requests, including JSON requests, and use a shared cache for limits across workers.

The default forced-password-reset destination now points to `auth/force-reset`.
PHP 8.2 is the package minimum.

## Maintenance and account data

Schedule `php spark auth:purge`. Remember-me cleanup and device-session retention
continue as before. Login and audit retention are optional, in days:

```php
public int $loginRetentionDays = 90;
public int $auditRetentionDays = 365;
```

Zero disables retention cleanup for that history. CLI overrides are
`auth:purge --logins 90 --audit 365`; recent records survive either operation. History deletion uses indexed batches of
1,000 rows; `--batch-size` accepts 1..10,000. Run the retention-index migration
before scheduling the cleanup. On large production tables, plan index creation
for your database engine and deployment window.

The account page offers a download of the signed-in user's data. A recent,
user-bound identity confirmation is required; the endpoint does not accept a
target user ID. The CLI and web download share `userDataExporter`. Authentication
secrets, active session IDs, and free-form audit metadata are redacted. The
service can be replaced to include application-owned personal data. Inline
exports are bounded to 1,000 rows per collection by default
(`AuthSecurity::$dataExportMaxRecords`, 1..5,000). Overflow returns an explicit
413 without a partial download. Applications needing larger exports should
provide an exporter suited to their storage and streaming requirements; the
CLI also reports overflow instead of silently truncating records.

## Passkey browser improvements

Conditional login integrates discoverable passkeys with the username field's
autofill. Explicit passkey sign-in stays available. Unsupported or cancelled
conditional operations do not interrupt password sign-in.

`AuthSecurity::$webauthnAutoUpgrade` is disabled by default. When enabled, the
account passkey panel attempts conditional credential creation after a recent
interactive login, provided the browser advertises `conditionalCreate` support.
Remember-me alone cannot authorize this automatic creation.

The credential synchronization endpoint is authenticated and returns only the
current user's active credential IDs. The browser uses
`PublicKeyCredential.signalAllAcceptedCredentials()` after deletion and on the
passkey panel to update its credential list. It never signals an unknown
credential merely because a signature, network request, or challenge failed.

These browser features follow the [W3C WebAuthn Level 3 specification](https://www.w3.org/TR/webauthn-3/)
and the [conditional creation explainer](https://github.com/w3c/webauthn/blob/main/explainers/conditional-create.md).
They are feature detected; support depends on the browser and credential provider.

## Verification

Run `vendor/bin/phpunit --no-coverage` and
`node --test tests/browser/passkey.test.mjs`. The browser-helper tests simulate
capabilities, ceremony requests, credential operations and CSRF rotation. Server
tests use a virtual authenticator with real signatures. Device-specific UX still
benefits from checking your supported browsers with a physical passkey.

# Memory — Gap re-check + code-health / extensibility pass (2026-10-23)

> Internal note (excluded from the published docs site). Read with
> `2026-10-13-feature-research.md`, which holds the sources and rationale.

## What shipped in this pass (3 stacked branches)

1. `fix/code-health-bugs` — banned users signed in through magic link/code,
   OAuth and passwordless passkeys (only `checkLogin()` / `AuthFilter` checked
   bans); reset flows skipped the password policy; unescaped flash messages in
   4 views; `_remap()` dispatched non-public methods; registration-disabled
   redirect loop; non-transactional admin group sync; misc. null/type bugs.
2. `refactor/code-cleanup` — de-duplication into protected helpers
   (stateless authenticators, 2FA actions, commands, OAuth, magic link,
   pivot entities, UUID models). `UserModelGeneratorTest` used to delete the
   real `src/Models/UserModel.php` and could lose it on an interrupted run.
3. `feat/extension-points` — `auditLogger` / `tokenEmailSender` /
   `passwordChangeRecorder` / `userAnonymizer` services, repository bindings
   actually used, custom User class honoured, protected hooks, lifecycle
   events, `failedLogin` finally fired for password logins, `docs/16-extending.md`.

## Gaps still open (re-verified 2026-10-23)

| Gap | Status | Evidence |
|---|---|---|
| `rates` on auth routes by default, IPv6 /64 + user+IP keys | Absent | `Auth::routes()` passes only `as`; `RatesFilter` keys on full IP |
| OAuth PKCE S256 / `iss` (RFC 9207) / OIDC nonce | Absent | `OauthManager::redirect()` calls `getAuthorizationUrl()` without options |
| Refresh-token reuse → revoke family | Partial | rotation + ban/password revocation exist; a replay is a plain 401 |
| Session idle / absolute timeouts | Absent | `AUTHENTICATED_AT_KEY` only used for step-up |
| Login / audit retention purge | Absent | `auth:purge` covers remember-me + device sessions only |
| Mandatory MFA policy | Absent | 2FA actions skip silently without an enrolled factor |
| Step-up with passkey/TOTP | Partial | `password-confirm` only accepts a password (hook: `PasswordConfirmFilter::confirmedRecently()`) |
| Passkey conditional UI / conditional create / Signal API | Absent | no `mediation` in `_webauthn_js.php` |
| Recovery codes for passkey-only 2FA; regenerate TOTP codes | Absent | backup codes are TOTP-only, generated once |
| "Trust this device" for Email2FA / Webauthn2FA | Absent | TOTP only (`Totp2FA` trusted-device block) |
| HMAC authenticator, impersonation, CAPTCHA hook, consent, webhooks, RFC 7662 introspection, OpenAPI, JSON form flows, self-service GDPR export, SAML/OIDC-SP, tenants, SCIM/LDAP | Absent | grep-verified |
| Self-service routes auto-registered | Absent | apps add the `security/*` group by hand |
| `Auth::$redirects['force_reset']` default `'/'` | Owner decision | the filter sends flagged users there; should probably be `auth/force-reset` |

## Gotchas learned

- Never edit `src/` while PHPUnit runs in the background: classes autoload
  lazily, and `UserModelGeneratorTest` rewrites `src/Models/UserModel.php`.
- `Auth::getProvider()` reads `$config->userProvider` (not `setting()`); tests
  that swap the provider must use `injectMockAttributes()` + `resetSingle('auth')`.
- PHPStan flags `Events::on()` closures with a required 2nd parameter
  (`callable(mixed): mixed`): give extra listener params defaults.
- deptrac: `Config` may depend on `Service` (Config\Services builds them).

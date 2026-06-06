# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Laravel-parity authentication & authorization
- **Gates & Policies** — class- and closure-based authorization layer inspired by Laravel's `Gate` facade:
  - `Daycry\Auth\Authorization\Gate` (`define()`, `policy()`, `allows()`, `denies()`, `authorize()`, `forUser()`, `has()`).
  - Abstract `Daycry\Auth\Authorization\Policy` with optional `before()` short-circuit hook.
  - `Daycry\Auth\Authorization\PolicyResponse` wrapping allow/deny + an optional message; `authorize()` chain-throws.
  - Auto-discovery via `Auth::$gateAutoDiscover` + `Auth::$policyNamespace` (default `App\\Policies\\`) — `App\Models\Post` resolves to `App\Policies\PostPolicy`.
  - `User::canDo()` / `User::cantDo()` on the `Authorizable` trait — sister methods to `can()` / `cant()` that route through the Gate (resource-aware) instead of through RBAC permissions (string-only). Existing `can()` signature untouched.
  - New `gate:ability1,ability2` filter alias backed by `GateFilter` for ability-only checks on routes.
  - `Daycry\Auth\Authorization\AuthorizationException` (separate from the existing `Exceptions\AuthorizationException` used by RBAC) carries the `PolicyResponse` for callers that need the deny message.
- **Password Confirmation workflow ("sudo mode")** — re-prompt the user for their password before sensitive actions:
  - New `password-confirm` filter alias backed by `PasswordConfirmFilter`.
  - New `UserSecurityController::confirmPasswordView()` + `confirmPasswordAction()` endpoints + `Views/confirm_password.php` form.
  - Setting `AuthSecurity::$passwordConfirmationLifetime` (default `3 * HOUR`; `0` = always require fresh confirmation; matches Laravel Fortify's default).
  - `EVENT_PASSWORD_CONFIRMED` added to `AuditLogger`.
- **HTTP Basic auth filter** — new `basic-auth` alias backed by `BasicAuthFilter` (RFC 7617). Reads `Authorization: Basic base64(user:pass)`, accepts email **or** username as identifier, persists into the session by default. Use `basic-auth:once` for stateless auth (no session write). Realm configurable via `Auth::$basicAuthRealm`. Designed for cron / health checks / internal tooling; **not** for user-facing routes.

#### 2FA UX
- **Backup codes for TOTP** — `auth_totp_backup_codes` table + `TotpBackupCodeModel` + `HasTotp::generateBackupCodes()` / `consumeBackupCode()` / `backupCodesRemaining()`. Codes generated on TOTP confirmation, shown once on the success page, used as fallback in `Totp2FA::verifyCodeForUser()` when the TOTP code itself does not match. Stored as SHA-256 hashes; one-time-use enforced atomically.
- **"Trust this device" 2FA bypass** — new `trusted_until` column on `auth_device_sessions`. After successful TOTP verification with the checkbox ticked, the device is marked trusted for `AuthSecurity::$trustedDeviceLifetime` (default 30 days) and a signed cookie is issued; subsequent logins from the same device skip the 2FA challenge. Setting `trustedDeviceLifetime = 0` disables the feature entirely.

#### Passwordless & passkeys
- **Magic Link — email code delivery mode** — the passwordless email login now offers **two delivery modes**: the existing one-time link and a new **6-digit code** mode. Both are for existing users only and anti-enumeration-safe (the response never reveals whether an email belongs to an account). Code mode is session-bound, hashed at rest, single-use, and subject to the per-user lockout. New settings `AuthSecurity::$magicLinkEnableLink` (default `true`), `AuthSecurity::$magicLinkEnableCode` (default `true`), `AuthSecurity::$magicCodeLifetime` (default `10 * MINUTE`); new `IdentityType::MAGIC_CODE`; new routes `GET/POST login/magic-link/code`. See the [Magic Link docs](https://github.com/daycry/auth/blob/development/docs/03-authentication.md#magic-link-authentication).
- **WebAuthn / Passkeys** — opt-in passwordless login and passkey-based second-factor, built on `web-auth/webauthn-lib`. Gated by `AuthSecurity::$webauthnEnabled` (default `false`; when off, `Auth::routes()` registers no `webauthn` routes and the controller 404s). New `auth_webauthn_credentials` table (migration `2026-06-03-000001`), `HasWebAuthn` trait on `User`, `WebAuthnManager` + `ChallengeManager` + `WebAuthnController` (JSON ceremonies), `WebAuthnCredentialRepository`, and a `Webauthn2FA` login action (mutually exclusive with `Totp2FA`). Passwordless verify ends in `auth()->login($user, false)`. See the [WebAuthn / Passkeys docs](https://github.com/daycry/auth/blob/development/docs/15-webauthn.md).

#### Security tooling
- **API token scope enforcement** — new `token-scope:scope1,scope2` filter alias backed by `TokenScopeFilter`. Validates `auth()->user()->currentAccessToken()->can($scope)` for every requested scope. Tokens with the `*` wildcard satisfy any check.
- **Suspicious login detection** — new `SuspiciousLoginDetector` service (`isNewIp()`, `isNewDevice()`, `analyse()`) compares each successful login's IP / User-Agent against the user's last 30 days of history. When `AuthSecurity::$suspiciousLoginAlerts = true`, fires the new `suspicious-login` event and writes an audit-log entry. New `Views/Email/suspicious_login_alert.php` template ships with the package; wire your own `Events::on('suspicious-login', ...)` listener to deliver it.
- **Compromised-password recheck on login** — opt-in (`AuthSecurity::$recheckPwnedOnLogin = true`). After a successful password verification, runs `PwnedValidator` against the live password and sets `force_reset` on the email_password identity if the password is in the HIBP breach corpus. Failures inside the recheck are logged and swallowed — login never blocks on HIBP availability.

#### Compliance
- **Granular audit log** — new `auth_audit_logs` table + `AuditLogModel` + `AuditLogger` service. Hooks recorded for: TOTP enable/disable, password reset/change, user lock/unlock, group/permission grant/revoke, token / refresh-token revoke, trusted-device add, suspicious login, admin TOTP reset, user anonymization. Lookups indexed by `(user_id, created_at)` and `(event_type, created_at)`.
- **Password history (NIST 800-63B SP §5.1.1.2)** — new `auth_password_history` table + `PasswordHistoryModel` + `HistoryValidator`. When `AuthSecurity::$passwordHistorySize > 0`, a new password is rejected if it matches any of the user's last N hashes; older entries are pruned automatically.
- **Periodic password rotation** — new `password_changed_at` column on `users`, new `password-age` filter alias backed by `PasswordAgeFilter`. When `AuthSecurity::$passwordMaxAge > 0` and the timestamp is older than that, the request is redirected to the force-reset flow.
- **GDPR export + anonymization** — new `auth:gdpr export -e <email> [-o <path>]` and `auth:gdpr anonymize -e <email>` commands. Export emits a JSON document with the user row, identities (with secrets redacted), device sessions, login history, audit log entries, and password-history / backup-code metadata. Anonymize replaces personal fields with placeholders, deletes identities/tokens/device sessions/password history/backup codes, and writes a final audit-log entry.

#### Quality-of-life
- **Per-user concurrent session limit** — new `Auth::$maxConcurrentSessions` (default 0 = unlimited). When > 0, the oldest active sessions are terminated on each new login so that no user has more than this many simultaneous sessions. Implemented via `DeviceSessionModel::enforceConcurrentSessionLimit()`.
- **User-facing login activity feed** — new `UserSecurityController::loginActivity()` + `Views/security/login_activity.php`. Lists the user's recent login attempts (success + failure) with timestamp, IP, and User-Agent so they can spot suspicious activity targeting their account.
- **CLI admin tools** — four new commands:
  - `auth:tokens revoke -e <email> [--type=access_token|jwt_refresh|all]`
  - `auth:sessions terminate -e <email>`
  - `auth:totp reset -e <email>`
  - `auth:audit [--since=24h] [--user=<email>] [--type=<event>]`

#### Token revocation & session integrity
- **JWT access-token revocation via `token_version`** — new `users.token_version` column (int, default `0`; migration `2026-05-08-000001_add_jwt_token_version_to_users`).
  - `JwtController` now mints the access-token payload as `{uid, tv}`, where `tv` is the user's current `token_version`. Legacy scalar payloads (a bare user id) are still accepted, with the `tv` check skipped.
  - The `JWT` authenticator's `check()` rejects any token whose embedded `tv` does not match the user's current `token_version`, returning `lang('Auth.revokedToken')`.
  - New `User::revokeIssuedTokens()` bumps `token_version` atomically (`set('token_version', 'token_version + 1', false)`), invalidating **all** outstanding access tokens — your "log out everywhere" primitive. Called automatically by `Bannable::ban()` and `Services\PasswordChangeRecorder::record()` (password reset/change).
  - `JwtController` now routes refresh / logout / issue through `service('jwtTokenRepository')`: refresh is one-time-use rotation, logout soft-revokes the refresh token.
- **Explicit OAuth account linking flow** — new authenticated route `GET oauth/link/(:segment) -> OauthController::link/$1` (route name `oauth-link`). Requires an authenticated user, stashes the current user (session key `oauth_link_user_id`), and links the provider to the **current** user on callback — no e-mail merge and no verified-email requirement, since the user is acting deliberately. Linking a social account already bound to a different local user is refused with `lang('Auth.oauthAlreadyLinked')`.
- **`auth:purge` maintenance command** — new `php spark auth:purge [--days <n>]` (group: `Auth`). Purges expired remember-me tokens (`auth_remember_tokens`) and terminated device sessions older than `--days` (default `30`). Intended to run on a schedule (cron / `daycry/jobs`) instead of the probabilistic on-login purge.

### Security

- **Bearer-token fingerprint logging** — `AccessToken` and `JWT` credentials written to the login-attempt log (`auth_logins.identifier`) are now stored as a non-reversible `hash('sha256', token)` fingerprint, never the raw bearer token. Session logins still log the email/username identifier (which is not a secret).
- **Remember-me expiry enforced at validation time** — `RememberMe::checkRememberMeToken()` now rejects an expired cookie outright; an expired remember-me cookie can no longer authenticate regardless of purge timing.
- **Remember-me theft detection** — when a token's selector matches but the validator does **not**, `RememberMe` purges **all** of that user's remember-me tokens (`RememberModel::purgeRememberTokensByUserId()`), writes an `AuditLogger::EVENT_SUSPICIOUS_LOGIN` (`'login.suspicious'`) entry, and fires `Events::trigger('remember-me-theft', $userId, $selector)`.
- **TOTP / backup-code lockout** — `Actions\Totp2FA::verify()` (via `User::verifyTotpCode()`) is now subject to the same per-user lockout as password login: `UserLockoutManager::recordFailedAttempt()` on a wrong code, `isLockedOut()` blocks, `resetOnSuccess()` clears the counter on success. Governed by the existing `AuthSecurity::$userMaxAttempts` / `$userLockoutTime`.
- **TOTP anti-replay** — a TOTP code is now single-use within its acceptance window. `TOTP::verifyAndGetTimestep()` returns the matched time-step, which is recorded in the TOTP secret identity's `extra` JSON; any code at or below the stored step is rejected. (`TOTP::verify()` behaviour is unchanged.)
- **OAuth verified-email guard for auto-linking** — when a social account's e-mail matches an existing local (password) account, auto-linking now only occurs if the provider asserts the e-mail is verified (OIDC `email_verified` / Google `verified_email`). Providers that cannot assert verification (Facebook, GitHub) refuse the merge — throwing `AuthenticationException` with `lang('Auth.oauthEmailUnverified')` — unless the per-provider `allowUnverifiedEmailLink` option is set.
- **Device-session revocation now invalidates the live session** — when `Auth::$sessionConfig['trackDeviceSessions']` is `true`, every authenticated request verifies that the current PHP session maps to a non-terminated `auth_device_sessions` row (`DeviceSessionModel::isSessionActive()`). A remotely-revoked or concurrent-limit-evicted session is now forced to re-authenticate. (Previously "revoke" only flipped a DB column while the cookie kept working.)
- **Magic-link & password-reset tokens stored hashed at rest** — `TokenEmailSender` stores `hash('sha256', token)` and `UserIdentityModel::getIdentityBySecret()` hashes the looked-up value; only the raw token is e-mailed, and single-use + expiry are enforced by the controllers. **Upgrade note:** any unconsumed magic-link / password-reset tokens issued before upgrade become invalid (users simply request a new link). This is a storage-format change in `auth_users_identities.secret` for those ephemeral types only — no destructive schema change.
- **Login identifiers (`username` + login email) now stored lowercase** — to make the login lookup index-friendly (see **Performance** above), `User::setUsername()`, `User::setEmail()`, and `UserIdentityModel::createEmailIdentity()` now lowercase the value at write time, and a data migration `2026-06-05-000002_normalize_login_identifiers` lowercases existing `users.username` and `email_password` identity secrets. Login stays case-insensitive. **Scope is strict:** OAuth social-ids, access-token / JWT-refresh hashes, magic-link / 2FA / reset codes, TOTP secrets, and WebAuthn credentials are untouched. **Upgrade note:** run `php spark migrate --all` **before** serving the new code (legacy mixed-case rows won't match until the data migration runs), and the migration **aborts without writing** — listing the offending rows — if it detects case-only duplicate accounts (e.g. `John` / `john`); it never merges or deletes accounts. Resolve the duplicate manually and re-run. See the [migration guide](https://github.com/daycry/auth/blob/development/docs/12-migration.md#login-identifiers-are-now-stored-lowercase).

- **Atomic per-user lockout counter** — `UserLockoutManager::recordFailedAttempt()` now uses a SQL increment expression (`failed_login_count = failed_login_count + 1`) and re-reads the post-increment value before deciding whether to lock the account. The previous read-modify-write pattern could lose concurrent failed-attempt updates, allowing more attempts than `userMaxAttempts` before lockout under load.
- **Timing-safe OAuth state comparison** — `OauthManager::handleCallback()` now compares the callback `state` against the session-stored value via `hash_equals()` instead of `!==`. Empty states and missing session values are also rejected explicitly. The check is followed by an empty-`code` guard.

### Performance

- **`last_used_at` write throttling for access tokens** — new `AuthSecurity::$tokenLastUsedThrottle` setting (default 60 seconds) prevents an UPDATE on every authenticated API request. Set to `0` to restore the previous always-write behaviour.
- **Composite index on `auth_identities(user_id, type, revoked_at)`** — new migration `2026-05-07-000001_add_identities_user_type_revoked_index` covers the common per-user identity-listing query shape. The existing `UNIQUE(type, secret)` continues to handle direct token lookups.
- **Faster UUID backfill** — `2026-02-28-000001_add_uuid_columns` now backfills via `updateBatch()` in chunks of 1 000, replacing the previous one-UPDATE-per-row loop. Idempotent on re-run (skips rows that already have a `uuid`).
- **Index-friendly login lookup** — `UserModel::findByCredentials()` no longer wraps the `username` column or the `email_password` identity `secret` in SQL `LOWER()`; it lowercases the **input** instead and matches the plain column, so the `UNIQUE(username)` and `UNIQUE(type, secret)` indexes are used. This relies on stored values being lowercase — see the **Security** entry below and the [migration guide](https://github.com/daycry/auth/blob/development/docs/12-migration.md#login-identifiers-are-now-stored-lowercase). Other (custom) `validFields` keep the `LOWER(column)` comparison and stay case-insensitive without normalization.
- **New session / login indexes** — new migration `2026-06-05-000001_add_session_and_login_indexes` adds covering indexes on `auth_device_sessions` and `auth_logins` for the device-session enforcement and login-history query shapes. No operator action required beyond running migrations.
- **Fewer queries on the hot path** — internal query reductions in access-token authentication and device-session enforcement. Drop-in; no operator action required.

### Changed

- **`auth()` facade now throws on missing methods** — `Daycry\Auth\Auth::__call()` throws `\BadMethodCallException` when the resolved authenticator does not implement the called method, instead of silently returning `null`. Methods available on every authenticator (Session / AccessToken / JWT): `attempt`, `check`, `getLogCredentials`, `getUser`, `loggedIn`, `login`, `loginById`, `logout`, `recordActiveDate`. Session-only methods: `checkAction`, `completeLogin`, `forget`, `getAction`, `getPendingMessage`, `getPendingUser`, `hasAction`, `isAnonymous`, `isPending`, `remember`, `startLogin`, `startUpAction`.
- **`User::can()` OR-semantics and wildcard fix** — `can(...string $permissions)` no longer aborts for group-less users when evaluating the variadic OR list, and scope wildcards (`posts.*`) now apply to **directly-assigned user** permissions as well as group permissions. So `$user->addPermission('posts.*')` followed by `$user->can('posts.create')` now returns `true`. Matching (`*`, exact, `scope.*`) is now uniform across user-level and group-level permissions.
- **Gate -> RBAC bridge** — a Gate ability whose name contains a scope (e.g. `users.edit`) with no registered closure/policy now falls back to `User::can()` when `AuthSecurity::$gateFallbackToRbac` is `true`, so `gate:users.edit` and `permission:users.edit` can share semantics. The `gate` filter honours this fallback.
- **Group/permission pivot persistence extracted to a repository** — transactional persistence of group/permission pivot rows now lives in `Daycry\Auth\Authorization\GroupPermissionRepository` (resolvable via `service('groupPermissionRepository')`). The `User` entity no longer opens DB transactions itself.
- **Token repositories are resolvable, overridable services** — `service('accessTokenRepository')`, `service('jwtTokenRepository')`, and `service('oauthTokenRepository')`.
- **`rates` filter honours per-route arguments** — `rates:<limit>,<period>` overrides the global limit/time for that route. `<period>` is a number of seconds or a named unit: `SECOND`, `MINUTE`, `HOUR`, `DAY`, `WEEK`. A configured endpoint DB row still overrides the per-route argument. (The registered alias is `rates`, not `auth-rates`.)
- **`password-confirm` filter honours a per-route lifetime** — `password-confirm:<seconds>` requires a password confirmation no older than `<seconds>` for that route, regardless of the global `AuthSecurity::$passwordConfirmationLifetime` ("sudo mode" for the most sensitive routes).
- **`BaseControllerTrait` end-of-request bookkeeping hardened** — moved into a public, idempotent `finalizeRequest()` wrapped in `try`/`catch`, so a logging failure can never become an uncatchable shutdown fatal. It can be called from an after-filter for deterministic timing.
- **UUID v7 generation now via `michalsn/codeigniter4-uuid`** — `service('uuid')->uuid7()->toRfc4122()` (declared in `composer.json` `require`; `symfony/uid` now arrives transitively). Used by `UserModel`, `DeviceSessionModel`, and the UUID backfill migration.
- **`AccessToken` authenticator now routes lookups through `AccessTokenRepository`** — uses a lazy-initialised repository getter instead of calling the deprecated `UserIdentityModel::getAccessTokenByRawToken()`. The repository owns the canonical query.
- **PwnedValidator HTTP timeouts** — new `AuthSecurity::$pwnedPasswordsConnectTimeout` (default 1.0s) and `$pwnedPasswordsTimeout` (default 3.0s) settings prevent registration / password-change flows from blocking when the HaveIBeenPwned API is slow.
- **TOTP verification window is configurable** — new `AuthSecurity::$totpWindow` (default 1 = ±30s, RFC 6238 default) replaces the hardcoded value. `User::verifyTotpCode($code)` resolves it from settings; pass an explicit `$window` to override.
- **`tests/_support/TestCase.php`** — added correctly-spelled `injectMockAttributes()`, `injectMockAttributesSecurity()`, `injectMockAttributesOAuth()`. The previous typo variants (`inkect*`) remain as deprecated aliases until v6.
- **DB failures in `DeviceSessionRecorder` are logged but no longer propagate** — device session tracking is non-critical and must not break login/logout.
- **JWT decode failures now log a `warning`-level message** — previously the exception was caught silently and only the error message was returned in the `Result`.

#### Configuration

New and changed settings on `Daycry\Auth\Config\AuthSecurity`:

| Setting | Default | Meaning |
|---------|---------|---------|
| `$activeDateThrottle` *(new)* | `60` | Minimum seconds between `users.last_active` writes on the authenticated hot path. `0` = write every request (legacy behaviour). |
| `$gateFallbackToRbac` *(new)* | `true` | A Gate ability containing a scope (`users.edit`) with no registered closure/policy falls back to `User::can()`. Set `false` to keep Gate and RBAC fully independent. |
| `$rememberMePurgeChance` *(changed)* | `0` (was `20`) | Expired remember-me tokens are now rejected at validation time regardless of this value; the inline probabilistic purge is now table maintenance only. Schedule `php spark auth:purge` instead. |
| `$userMaxAttempts` / `$userLockoutTime` *(scope expanded)* | `5` / `3600` | Per-user lockout — now **also** applied to TOTP / backup-code verification. |
| `$passwordConfirmationLifetime` *(scope expanded)* | (existing) | Sudo-mode window — now overridable per route via the `password-confirm:<seconds>` filter argument. |

New per-provider option on `Daycry\Auth\Config\AuthOAuth::$providers`:

| Option | Default | Meaning |
|--------|---------|---------|
| `allowUnverifiedEmailLink` | unset (`false`) | When the social account's e-mail matches an existing local (password) account, auto-linking is normally only performed if the provider asserts the e-mail is verified. Set `true` per provider to allow the merge even for providers that cannot assert verification (Facebook, GitHub). |

### Fixed

- **`User::can()` aborted the OR-check for group-less users** — the variadic OR list short-circuited incorrectly when the user belonged to no group; it now evaluates directly-assigned permissions for every term in the list.
- **`User::can()` ignored scope wildcards on directly-assigned permissions** — a directly-assigned `posts.*` permission did not satisfy `can('posts.create')`; wildcard matching is now uniform across user-level and group-level permissions.
- **`auth()` facade swallowed unknown method calls** — calling a method the active authenticator does not implement returned `null` silently; it now throws `\BadMethodCallException`.
- **Device-session "revoke" did not end the live session** — revoking a session only flipped a DB column while the existing cookie continued to authenticate; revoked / evicted sessions are now forced to re-authenticate.

### Migration

- **`2026-05-08-000001_add_jwt_token_version_to_users`** — adds `users.token_version` (int, default `0`).
- **Token storage format change (no schema migration)** — magic-link and password-reset tokens are now stored SHA-256-hashed in `auth_users_identities.secret`. Any unconsumed tokens issued before upgrade become invalid; affected users simply request a new link.

### Tooling

- Fixed `deduplicate` script paths.
- Corrected `.gitattributes` (`deptrac.yaml`, `phpstan-baseline.neon`, and the `phpcpd` phar now `export-ignore`).
- CI composer cache keyed on `composer.json`.
- `composer.json`: minimum PHP raised to `^8.2`.

### Documentation

- `docs/02-configuration.md` — documents `tokenLastUsedThrottle`, `pwnedPasswordsTimeout`, `pwnedPasswordsConnectTimeout`.
- `docs/10-totp-2fa.md` — documents `totpWindow`.
- `docs/08-testing.md` — examples updated to the correctly-spelled `injectMockAttributes*` helpers.
- `Config/AuthSecurity.php` — clearer guidance on enabling `permissionCacheEnabled` in production.

## [5.0.0] - 2026-03-20

### Added

#### OAuth Profile System
- **`IdentityType::oauthProvider()` static helper** — centralises the `oauth_` prefix convention for dynamic provider types. Replaces all manual `'oauth_' . $provider` string concatenation across the codebase.
- **`OAuthTokenRepository`** (`src/Models/OAuthTokenRepository.php`) — dedicated repository for OAuth identity CRUD, following the same pattern as `AccessTokenRepository` and `JwtTokenRepository`. Methods:
  - `findByUserAndProvider(int $userId, string $provider)` — find OAuth identity for a user/provider pair
  - `findByProviderAndSocialId(string $provider, string $socialId)` — find by provider type and social ID
  - `createOAuthIdentity(int $userId, string $provider, array $data)` — insert a new OAuth identity
  - `updateOAuthIdentity(UserIdentity $identity)` — update an existing identity (token refresh, re-login)
  - `getProfileData(int $userId, string $provider)` — get stored profile data from the `extra` JSON
  - `parseExtra(?string $extra)` — parse `extra` column with backward compatibility (JSON and legacy plain-string format)
- **Config-based `ProfileResolverFactory`** — the factory now accepts `$providerConfig` and resolves profile resolvers in this order:
  1. `$providerConfig['profileResolver']` — custom class (must implement `ProfileResolverInterface`, throws `LogicException` otherwise)
  2. Built-in map (`azure` -> `AzureProfileResolver`)
  3. Fallback -> `GenericProfileResolver`
- **`profileResolver` config key** — documented in `AuthOAuth.php` Generic provider example; allows per-provider custom resolver classes
- **`scopes_granted` in extra JSON** — when the OAuth provider returns granted scopes in the token response (RFC 6749 SS3.3), they are stored as an array in the identity's `extra` JSON. Updated on both initial login and token refresh.
- **`profile_fetched_at` in extra JSON** — ISO 8601 timestamp recorded when profile fields are fetched, allowing consumers to know the freshness of cached profile data

#### OAuth Events
- **`oauth-login`** — fired after every successful OAuth login via `handleCallback()`. Arguments: `User $user`, `string $providerName`
- **`oauth-profile-fetched`** — fired when profile fields were resolved (only when `fields` is configured and data was returned). Arguments: `User $user`, `string $providerName`, `array $profileData`

#### Tests
- `tests/Models/OAuthTokenRepositoryTest.php` — 10 tests covering find, create, update, getProfileData, parseExtra (JSON, legacy, empty)
- `tests/Libraries/Oauth/ProfileResolver/ProfileResolverFactoryTest.php` — 4 tests: Azure resolver, generic fallback, config-based override, invalid resolver throws
- `tests/Libraries/OauthManagerTest.php` — 9 new tests:
  - `testRefreshAccessTokenWithJsonExtra` / `testRefreshAccessTokenWithLegacyExtra` — refresh with JSON and legacy extra formats
  - `testRefreshAccessTokenNoIdentity` / `testRefreshAccessTokenProviderFails` — null return cases
  - `testHandleCallbackTriggersOauthLoginEvent` / `testHandleCallbackTriggersProfileFetchedEvent` — event verification
  - `testHandleCallbackStoresScopesGranted` — scopes extracted from token response
  - `testHandleCallbackStoresProfileFetchedAt` — timestamp presence in extra JSON

#### Documentation
- `docs/09-oauth.md` — complete rewrite with 11 new sections: Architecture, Stored Token Data (JSON structure), Profile Fields (configuring, resolvers, custom resolver, reading data), Scopes Granted, OAuth Events, OAuthTokenRepository reference, IdentityType Helper, Testing OAuth
- `docs/02-configuration.md` — updated OAuth section with `fields`, `fieldsEndpoint`, `profileResolver` keys; added Provider Configuration Keys reference table
- `docs/07-logging.md` — added `oauth-login` and `oauth-profile-fetched` to the events table; added OAuth Login Tracking section
- `docs/08-testing.md` — added OAuth test file references
- `docs/README.md` — added OAuth Profile Fields & Resolvers, OAuth Events, OAuthTokenRepository to Feature Matrix
- `docs/index.md` — updated OAuth description
- `CLAUDE.md` — added `OAuthTokenRepository` to source layout and token repositories table; expanded OAuth2 section

### Changed

- **`OauthManager` refactored** — all identity CRUD delegated to `OAuthTokenRepository` via lazy-initialised `getRepository()` getter. Centralised `getIdentityModel()` replaces three separate `model(UserIdentityModel::class)` calls. Removed private `parseExtra()` method (now public on repository).
- **`ProfileResolverFactory::create()` signature** — now accepts optional `array $providerConfig = []` as second parameter for config-based resolver resolution
- **`OauthManager::processUser()`** — login event (`auth()->login()`) no longer triggers events itself; `oauth-login` and `oauth-profile-fetched` are fired from `handleCallback()` after `processUser()` returns
- **`OauthManager::refreshAccessToken()`** — now updates `scopes_granted` in extra JSON when the refreshed token includes scope information
- PHPStan baseline regenerated: 599 -> 627 suppressions (new repository, tests, Mockery patterns)

## [4.0.0] - 2026-03-01

### Breaking Changes

- **`Config\Auth` split into three classes** — security and OAuth settings have been extracted into dedicated config files. Applications that extend or override `app/Config/Auth.php` must migrate the affected properties:

  | Property | Old class | New class |
  |----------|-----------|-----------|
  | `$minimumPasswordLength`, `$passwordValidators`, `$maxSimilarity` | `Auth` | `AuthSecurity` |
  | `$hashAlgorithm`, `$hashCost`, `$hashMemoryCost`, `$hashTimeCost`, `$hashThreads` | `Auth` | `AuthSecurity` |
  | `$supportOldDangerousPassword` | `Auth` | `AuthSecurity` |
  | `$recordLoginAttempt`, `$recordActiveDate`, `$enableLogs` | `Auth` | `AuthSecurity` |
  | `$userMaxAttempts`, `$userLockoutTime` | `Auth` | `AuthSecurity` |
  | `$enableInvalidAttempts`, `$maxAttempts`, `$timeBlocked` | `Auth` | `AuthSecurity` |
  | `$limitMethod`, `$requestLimit`, `$timeLimit` | `Auth` | `AuthSecurity` |
  | `$accessTokenEnabled`, `$unusedAccessTokenLifetime`, `$strictApiAndAuth` | `Auth` | `AuthSecurity` |
  | `$allowMagicLinkLogins`, `$magicLinkLifetime` | `Auth` | `AuthSecurity` |
  | `$passwordResetLifetime`, `$jwtRefreshLifetime` | `Auth` | `AuthSecurity` |
  | `$totpIssuer`, `$permissionCacheEnabled`, `$permissionCacheTTL` | `Auth` | `AuthSecurity` |
  | `RECORD_LOGIN_ATTEMPT_*` constants | `Auth` | `AuthSecurity` |
  | `$providers` | `Auth` | `AuthOAuth` |

- **`setting('Auth.X')` calls renamed** — any custom code using `setting('Auth.recordLoginAttempt')`, `setting('Auth.requestLimit')`, etc. must update to `setting('AuthSecurity.X')` or `setting('AuthOAuth.X')` accordingly.
- **`Passwords` and `BaseValidator` constructor** now accept `AuthSecurity` instead of `Auth`. Custom password validators extending `BaseValidator` must update their type hints.
- **`OauthManager` constructor** now accepts `AuthOAuth` instead of `Auth`.

### Migration

Create `app/Config/AuthSecurity.php` and `app/Config/AuthOAuth.php` extending the library classes, then move your customised properties into the respective files:

```php
// app/Config/AuthSecurity.php
namespace Config;
use Daycry\Auth\Config\AuthSecurity as AuthSecurityConfig;
class AuthSecurity extends AuthSecurityConfig
{
    public int $minimumPasswordLength = 10;
    // ...
}

// app/Config/AuthOAuth.php
namespace Config;
use Daycry\Auth\Config\AuthOAuth as AuthOAuthConfig;
class AuthOAuth extends AuthOAuthConfig
{
    public array $providers = [ /* your providers */ ];
}
```

---

## [3.1.0] - 2026-02-28

### Added

#### Authentication
- **TOTP Two-Factor Authentication** — time-based OTP compatible with Google Authenticator, Authy, and 1Password
  - `src/Libraries/TOTP.php` — secret generation, QR code URI, and code verification (RFC 6238)
  - `src/Traits/HasTotp.php` — `enableTotp()`, `disableTotp()`, `verifyTotp()` mixed into `User`
  - `src/Authentication/Actions/Totp2FA.php` — post-login action: validates TOTP code before granting session
  - `src/Views/totp_2fa_verify.php`, `totp_setup_show.php`, `totp_setup_success.php` — enrollment and verification views
- **JWT Refresh Token rotation** — stateless token renewal without re-authentication
  - `src/Controllers/JwtController.php` — `loginAction()`, `refreshAction()`, `logoutAction()`
  - Refresh token stored as hashed `access_token` identity; revoked on logout
- **Password Reset flow** — secure token-based reset with email delivery
  - `src/Controllers/PasswordResetController.php` — request, message, and reset views + actions
  - `src/Views/password_reset_request.php`, `password_reset_message.php`, `password_reset_form.php`
  - `src/Views/Email/password_reset_email.php` — HTML reset email template
- **Force Password Reset** — mandatory password change on next login
  - `src/Controllers/ForcePasswordResetController.php` — intercepts login and forces password update
  - `src/Views/force_password_reset.php` — password change form
  - `src/Filters/ForcePasswordResetFilter::class` — route filter alias `force-reset`
- **Email change confirmation** — `src/Views/Email/email_change_email.php` — confirmation link sent to new address

#### Security
- **Device Session Tracking** — see and terminate active logins per device/browser
  - `src/Database/Migrations/2026-02-26-000002_create_device_sessions_table.php`
  - `src/Models/DeviceSessionModel.php` — CRUD + cleanup helpers
  - `src/Entities/DeviceSession.php` — entity with UA parsing, IP, last active
  - `src/Traits/HasDeviceSessions.php` — `getDeviceSessions()`, `terminateDeviceSession()`, `terminateAllDeviceSessions()`
  - Session authenticator integration: creates record on login, terminates on logout
- **UUID Dual-Key Pattern** — expose `uuid` externally, keep `id` (INT) internal
  - `src/Database/Migrations/2026-02-28-000001_add_uuid_columns.php` — adds `uuid VARCHAR(36) UNIQUE` to `users` and `device_sessions`; backfills existing rows with UUID v7
  - `UserModel` and `DeviceSessionModel` — `$beforeInsert` callback generates UUID v7 via `symfony/uid`
- **Per-user account lockout** — independent of IP blocking
  - `src/Database/Migrations/2026-02-28-000002_add_security_columns.php` — adds `last_login`, `failed_login_attempts`, `last_failed_login` columns to users
  - `Session` authenticator tracks and checks per-user failed attempts
- **Performance indexes** — `src/Database/Migrations/2026-02-26-000001_add_performance_indexes.php` — indexes on `auth_users_identities`, `auth_logins`, `auth_remember_tokens`
- `hash_equals()` for timing-safe token comparison in `Session` authenticator (prevents timing attacks)
- `json_decode()` / `json_encode()` replacing `unserialize()` / `serialize()` in `SerializeCast`, `UserIdentityModel`, and `Logger` (prevents object injection)

#### Authorization
- **Permission Cache** — configurable TTL cache in `Authorizable` trait
  - `Config\Auth::$permissionCacheEnabled` (default `false`) and `$permissionCacheTTL` (default `300` seconds)
  - Auto-invalidated on any group/permission change; manual `$user->clearPermissionCache()`

#### Admin Panel (Bootstrap 5)
- `src/Controllers/Admin/DashboardController.php` — overview stats
- `src/Controllers/Admin/UsersController.php` — list, show, edit, ban/unban, force-reset
- `src/Controllers/Admin/GroupsController.php` — create, edit, delete groups; manage members
- `src/Controllers/Admin/PermissionsController.php` — create, edit, delete permissions
- `src/Controllers/Admin/LogsController.php` — paginated login attempt log viewer
- `src/Views/admin/` — Bootstrap 5 layout, dashboard, users, groups, permissions, logs views

#### OAuth2
- `src/Controllers/UserSecurityController.php` — user-facing TOTP management, device session list, OAuth provider unlinking, password and email change
- `src/Views/profile/security.php` — security settings page
- OAuth refresh token storage and retrieval via `UserIdentityModel`

#### Architecture
- `src/Authentication/Authenticators/StatelessAuthenticator.php` — abstract base for `AccessToken` and `JWT`; centralises `login()`, `logout()`, `loggedIn()`, `loginById()`, removing ~120 lines of duplication
- `src/Enums/IdentityType.php` — new values: `TotpSecret`, `Totp`, `RefreshToken`
- `src/Interfaces/UserProviderInterface.php` — added `update()` method

#### Language
- All 19 language files (`en`, `ar`, `bg`, `de`, `es`, `fa`, `fr`, `id`, `it`, `ja`, `lt`, `pt`, `pt-BR`, `ru`, `sk`, `sr`, `sv-SE`, `tr`, `uk`) updated with strings for:
  - TOTP enrollment and verification
  - Device session management
  - Password reset and force-reset flow
  - Per-user lockout messages

#### CI / Tooling
- `.github/workflows/phpunit.yml` — overhauled:
  - Fixed deprecated `::set-output` → `$GITHUB_OUTPUT`
  - Updated `actions/checkout` and `actions/cache` to `@v4`
  - Added `development` branch to push/PR triggers
  - Removed unnecessary `script -e -c` PTY wrapper
  - Separated coverage into a dedicated `coverage` job (PHP 8.3 + Xdebug only); test matrix runs with `coverage: none`
  - Per-PHP-version composer cache keys
  - Replaced manual `php-coveralls` install with `coverallsapp/github-action@v2`
- `.github/workflows/static-analysis.yml` — new pipeline with three parallel jobs:
  - `phpstan` — PHPStan level 5 with result cache
  - `cs` — PHP CS Fixer dry-run
  - `deptrac` — architecture compliance check
- `deptrac.yaml` — added `Library` to allowed dependencies of `Entity` layer (required by `HasTotp` trait)

#### Documentation
- Complete rewrite of `docs/` (11 sections):
  - `01-quick-start.md` — includes password reset routes and filter setup
  - `02-configuration.md` — all new options: `passwordResetLifetime`, `jwtRefreshLifetime`, `userMaxAttempts`, `userLockoutTime`, `permissionCacheEnabled`, `trackDeviceSessions`
  - `03-authentication.md` — JWT refresh token rotation, per-user lockout, password reset, force reset, pre-auth events
  - `05-controllers.md` — `PasswordResetController`, `ForcePasswordResetController`, `JwtController`, `UserSecurityController`
  - `06-authorization.md` — permission cache, RBAC patterns, admin panel
  - `07-logging.md` — CI4 Events table, per-user lockout, rate limiting
  - `09-oauth.md` — provider setup, refresh tokens, unlinking
  - `10-totp-2fa.md` *(new)* — full enrollment and login flow
  - `11-device-sessions.md` *(new)* — session tracking, termination, notifications
- Root `README.md` — feature tables, JWT refresh example, updated badges (Tests + Static Analysis)

### Changed

- `AccessToken` and `JWT` authenticators refactored to extend `StatelessAuthenticator`
- `Authorizable` trait centralised model access via private getter methods (reduces PHPStan `model()` warnings)
- `Activatable`, `Bannable`, `Resettable` traits: removed direct `auth()->getProvider()` calls, use internal model getter
- `GroupFilter` and `PermissionFilter` extended from `AbstractAuthFilter` (DRY refactor)
- `ExceptionHandler` simplified — removed duplicate `handle()` overloads
- PHPStan baseline regenerated: 587 → 599 suppressions (new controllers absorb `model()`/`emailer()` discouraged-call warnings)
- PHP CS Fixer: matrix test versions changed from `['8.1', '8.2', '8.3']` to `['8.2', '8.3']`

### Fixed

- Language files (18 non-English): unescaped apostrophes (`we'll`, `wasn't`) in single-quoted PHP strings causing `ParseError`
- `UserProviderInterface` missing `update()` method — caused PHPStan errors in `Session` authenticator per-user lockout code
- `ForcePasswordResetController::getValidationRules()` incorrect PHPDoc return type

[Unreleased]: https://github.com/daycry/auth/compare/v5.0.0...HEAD
[5.0.0]: https://github.com/daycry/auth/compare/v4.0.0...v5.0.0
[4.0.0]: https://github.com/daycry/auth/compare/v3.1.0...v4.0.0
[3.1.0]: https://github.com/daycry/auth/compare/v3.0.6...v3.1.0

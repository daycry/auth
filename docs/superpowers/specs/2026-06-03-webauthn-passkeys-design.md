# WebAuthn / Passkeys — design (2026-06-03)

Dedicated cycle for the WebAuthn/Passkeys feature deferred from the
2026-06-02 auth-improvements roadmap. Implemented with TDD (RED→GREEN).
This is a new, security-critical feature for a widely-used library — the bar
is zero defects and full security-invariant coverage.

## Goal & product semantics

Add FIDO2/WebAuthn support so users can authenticate with passkeys
(public-key credentials) instead of, or in addition to, passwords.

Two axes, kept strictly separate:

- **Availability (global flag)** — `AuthSecurity.$webauthnEnabled` (default
  `false`). OFF ⇒ the feature does not exist: no routes are registered and every
  endpoint 404s. ON ⇒ users who want to can enrol a passkey (opt-in, per user).
- **Enforcement (obligatoriness)** — a separate axis, **out of scope for v1**
  (YAGNI). The architecture leaves room for a later per-role "require passkey"
  policy, but v1 ships availability + opt-in enrolment only.

## Scope (v1)

Both integration models, since the stored credential is identical for both:

- **Passwordless login** — usernameless/discoverable assertion → establishes a
  session. The headline passkey experience.
- **Second factor (2FA)** — passkey assertion *after* password, via the existing
  post-auth Action system (mirrors `Totp2FA`).

**Non-goals (v1):** enforcement policy; passkey-only accounts with no password
fallback; attestation-based authenticator allow/deny lists; cross-device hybrid
transport UI beyond what the browser provides natively; admin-panel management
of *other* users' credentials.

## Key decisions (approved)

| Decision | Choice | Rationale |
|---|---|---|
| Integration model | Passwordless **and** 2FA | Same credential storage; max coverage. |
| Storage | Dedicated table `auth_webauthn_credentials` | Matches existing dedicated-table pattern (`device_sessions`, `totp_backup_codes`, `password_history`); `sign_count`/`credential_id` need an indexed column, not JSON. |
| Crypto library | `web-auth/webauthn-lib` (Spomky-Labs) | Conformance-tested, most complete. **Risk:** pulls symfony deps — must resolve cleanly on a clean CI4 app (validated as plan step 0). |
| Architecture | Mirror the OAuth pattern | `OauthManager`/`OauthController`/`OAuthTokenRepository` end in a Session login; WebAuthn is the same shape. No new chain authenticator. |
| Frontend | Reference views + vanilla JS over JSON endpoints | Consistent with the library's batteries-included, overridable views; SPAs can call the JSON endpoints directly. |

## Architecture

Mirrors the established **OAuth** pattern (a manager + controller + repository
ceremony that terminates in a `Session` login) rather than introducing a
chain authenticator — passwordless WebAuthn always yields a session, never a
per-request token.

New files (each follows an existing sibling pattern):

```
src/
├── Authentication/
│   ├── Actions/Webauthn2FA.php           # 2FA action            (≈ Totp2FA)
│   └── WebAuthn/
│       ├── WebAuthnManager.php           # orchestrates ceremonies (≈ OauthManager)
│       └── ChallengeManager.php          # per-ceremony challenge: single-use + TTL (session-backed)
├── Controllers/WebAuthnController.php     # ceremony endpoints (JSON)
├── Entities/WebAuthnCredential.php
├── Models/
│   ├── WebAuthnCredentialModel.php        # CRUD + UUID v7        (≈ DeviceSessionModel)
│   └── WebAuthnCredentialRepository.php   # implements the lib's credential-source repo interface (≈ OAuthTokenRepository)
├── Traits/HasWebAuthn.php                 # User: webAuthnCredentials(), revoke…()  (≈ HasTotp)
├── Database/Migrations/2026-06-03-000001_create_webauthn_credentials.php  # date = creation day
└── Views/
    ├── webauthn_setup.php                 # enrolment widget (security overview)
    ├── webauthn_2fa_verify.php            # 2FA challenge page
    └── _webauthn_js.php                   # shared base64url/ceremony JS partial
```

`WebAuthnCredentialRepository` implements the credential-source repository
interface required by `web-auth/webauthn-lib` (exact interface name pinned when
the dependency is resolved), backed by `WebAuthnCredentialModel`. It is the
bridge between the crypto library and persistence. Layering for deptrac:
`Controller → Manager → Repository → Model`; no `Entity → DB`.

## Data schema

New table **`auth_webauthn_credentials`** (configurable via
`Config\Auth::$tables['webauthn_credentials'] = 'auth_webauthn_credentials'`):

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK AI | |
| `uuid` | UUID v7 | external reference, consistent with other tables |
| `user_id` | BIGINT UNSIGNED, FK→users, **index** | one user → many credentials |
| `credential_id` | VARCHAR(512), **UNIQUE** | `rawId` base64url; lookup key on every assertion |
| `credential` | TEXT/JSON | **source of truth**: serialized `PublicKeyCredentialSource` (all crypto reads this) |
| `user_handle` | VARCHAR(255) | opaque WebAuthn user handle = `users.uuid` (non-PII); usernameless lookup |
| `name` | VARCHAR(255), nullable | user label ("Juan's iPhone") |
| `sign_count` | INT UNSIGNED | denormalized mirror for display/audit |
| `transports` | VARCHAR/JSON | `allowCredentials` hint |
| `aaguid` | VARCHAR(64) | authenticator model id (display) |
| `last_used_at` | DATETIME, nullable | |
| `created_at` / `updated_at` | DATETIME | |
| `revoked_at` | DATETIME, nullable | soft-revoke, consistent with identities/tokens |

Crypto **always** operates on the serialized `credential`; the other columns are
denormalizations written at insert (and `sign_count`/`last_used_at` updated on
each successful assertion).

`IdentityType::WEBAUTHN = 'webauthn'` is added but used **only** as the
pending-action marker in `auth_users_identities` (like `Totp2FA`), never to store
a credential.

## Config

`AuthSecurity` additions:

| Setting | Default | Purpose |
|---|---|---|
| `$webauthnEnabled` | `false` | Global availability flag. |
| `$webauthnRelyingPartyId` | `null` → request host | `rpId` (origin binding domain). |
| `$webauthnRelyingPartyName` | `'Daycry Auth'` | display name shown in the browser prompt. |
| `$webauthnAllowedOrigins` | `[]` → derived from `base_url` | valid origins (subdomains / native apps). |
| `$webauthnUserVerification` | `'preferred'` | `required`/`preferred`/`discouraged`. Recommend `required` for passwordless. |
| `$webauthnResidentKey` | `'preferred'` | discoverable credential (needed for usernameless). |
| `$webauthnAttestationConveyance` | `'none'` | privacy by default. |
| `$webauthnAuthenticatorAttachment` | `null` | `null`=both, `platform`, `cross-platform`. |
| `$webauthnTimeout` | `60000` (ms) | ceremony timeout. |
| `$webauthnChallengeTtl` | `120` (s) | challenge validity (single-use). |
| `$webauthnMaxCredentialsPerUser` | `10` | per-user cap. |

`Config\Auth` additions: the `$routes['webauthn']` group; `$views` keys
`webauthn_setup`, `webauthn_2fa_verify`; documents `Webauthn2FA::class` as a
`$actions['login']` option.

**Route gating:** `Auth::routes()` adds the `webauthn` group **only when**
`webauthnEnabled` is true; every controller method also re-checks the flag and
returns **404** when OFF (defense in depth). OFF ⇒ the feature is invisible.

## Routes & JSON contracts

```
# Enrolment (requires an active session)
POST webauthn/register/options   → WebAuthnController::registerOptions
POST webauthn/register/verify    → WebAuthnController::registerVerify
# Passwordless login (no prior auth)
POST webauthn/login/options      → WebAuthnController::loginOptions
POST webauthn/login/verify       → WebAuthnController::loginVerify
# 2FA (pending login in session); verify goes through the Action system
POST webauthn/2fa/options        → WebAuthnController::twoFactorOptions
# Management (requires session)
POST webauthn/credentials/(:segment)/delete → WebAuthnController::deleteCredential/$1
```

The credential list renders inside `security_overview` (served by
`UserSecurityController`, like TOTP status); add/remove happen via these JSON
endpoints called by JS on that page.

Contracts:

- **registerOptions** → 200 `PublicKeyCredentialCreationOptions` (challenge, rp,
  user, `excludeCredentials`=user's active credentials, `authenticatorSelection`,
  `attestation:none`); challenge stored in session. Errors: 403 no session, 409
  cap reached, 404 disabled.
- **registerVerify** ← `{id,rawId,type,response:{clientDataJSON,attestationObject},name?}`
  → 201 `{status:"ok",credential:{uuid,name,created_at}}`. Errors: 422
  verification failed, 409 duplicate credential, 400 malformed.
- **loginOptions** ← `{email?}` → 200 `PublicKeyCredentialRequestOptions`. No
  email ⇒ usernameless (`allowCredentials` empty, discoverable). **Anti-enumeration:**
  an unknown email returns options with empty `allowCredentials` (does not reveal
  existence).
- **loginVerify** ← `{id,rawId,type,response:{clientDataJSON,authenticatorData,signature,userHandle}}`
  → look up credential by `rawId` → user → verify assertion + challenge +
  `sign_count` → `Session::login($user, actions:false)` → 200
  `{status:"ok",redirect}`. Errors: 422 generic (never leaks which check failed),
  401 no matching credential, 404 disabled.
- **twoFactorOptions** → request options scoped to the pending user (from
  session); 422 if no pending login.
- **deleteCredential** → soft-revoke by uuid (must belong to the user); 200
  `{status:"ok"}`.

## Ceremonies

`ChallengeManager` stores the **full serialized options** (the crypto library
needs the original `…CreationOptions`/`…RequestOptions` to validate), plus
`type` (`register|login|2fa`), `user_id` (register/2fa), and `created_at`. It is
**deleted on verify** (single-use) and rejected when `now − created_at >
challengeTtl`. A test seam allows pinning the challenge.

**Enrolment** (logged-in user):
1. JS → `register/options {name?}`. Server validates flag + session + cap; builds
   `CreationOptions` with `user.id = users.uuid` (opaque handle), `excludeCredentials`
   = active credentials, `pubKeyCredParams` (ES256, RS256), `authenticatorSelection`,
   `attestation:none`; stashes options; returns JSON.
2. JS → `navigator.credentials.create()` → attestation → `register/verify`.
3. Server loads + **deletes** the session options (`type=register`, not expired,
   user = logged-in), validates attestation via the lib (clientDataJSON
   `type=webauthn.create`, challenge, origin, rpIdHash), checks `credential_id`
   uniqueness, persists the serialized source + denormalized columns, audit-logs
   `WEBAUTHN_REGISTERED`, returns 201.

**Passwordless login** (no prior auth):
1. JS → `login/options {email?}`. With a known email ⇒ `allowCredentials` = that
   user's credentials; otherwise empty (discoverable/usernameless; anti-enumeration).
   Stash options (`type=login`, no user binding).
2. JS → `navigator.credentials.get()` → assertion (carries `userHandle` for
   discoverable) → `login/verify`.
3. Server loads + **deletes** options (`type=login`, not expired); looks up the
   credential by `rawId` (active); reconstructs the source; validates the assertion
   (clientDataJSON `type=webauthn.get`, challenge, origin, rpIdHash, UV per policy,
   **signature** against the stored public key, `userHandle` == stored
   `user_handle`); applies `sign_count` anti-clone; updates `sign_count` +
   `last_used_at`.
4. **A verified passkey (with user verification) is already multi-factor**
   (possession + biometric), so passwordless login **completes the session
   directly** via `Session::login($user, actions:false)` — it does **not** re-run
   the 'login' Action pipeline (which would redundantly re-challenge).
   `userVerification:'required'` is recommended for passwordless. Device session +
   audit are recorded as usual.

**2FA** (`Webauthn2FA`, after password):
- `createIdentity()` inserts the `IdentityType::WEBAUTHN` marker only if the user
  has ≥1 active credential and is not on a trusted device → redirect to
  `auth-action-show`.
- `show()` renders `webauthn_2fa_verify`; its JS fetches `2fa/options` (scoped to
  the **pending** user, `type=2fa` with binding) and posts the assertion to
  `auth/a/verify`.
- `verify()` applies the **same `UserLockoutManager`** as password login; validates
  the assertion requiring the credential to belong to the pending user; on success
  → `resetOnSuccess` + delete marker + `completeLogin`; on failure →
  `recordFailedAttempt` + error.
- Only one Action per event is supported (existing constraint), so WebAuthn-2FA and
  TOTP-2FA are mutually exclusive as the login action in v1.

## Security invariants

Verified on every ceremony; each has a dedicated negative test.

| # | Invariant |
|---|---|
| 1 | Server-generated challenge (≥16 bytes), **single-use**, TTL-bounded, bound to `type` (and user for register/2fa). |
| 2 | **Origin binding**: `clientDataJSON.origin` ∈ `webauthnAllowedOrigins` (anti-phishing). |
| 3 | **rpId binding**: `rpIdHash` == SHA-256(`rpId`). |
| 4 | **User verification** enforced per `webauthnUserVerification` (UV flag when `required`). |
| 5 | **Signature** verified against the stored COSE public key. |
| 6 | **Anti-clone**: reject when new `counter` ≤ stored (except the 0/0 case); update on success. |
| 7 | **userHandle** returned (if any) == credential's `user_handle`. |
| 8 | **Ownership**: in 2FA/delete the credential must belong to the (pending/logged-in) user. |
| 9 | **Revoked** credentials excluded from lookup and `allowCredentials`. |
| 10 | **Anti-enumeration**: `login/options` never reveals whether an email exists; verify errors are generic. |
| 11 | **Lockout**: 2FA failures feed the same `UserLockoutManager` as password failures. |
| 12 | **CSRF**: CI4 CSRF active on POSTs; the ceremony is additionally CSRF-resistant via the challenge. |

**Error shape:** endpoints return `{status:"error", error:"<code>", message:"<human>"}`
with appropriate HTTP codes (400/401/403/404/409/422). Verification failures are
logged with detail server-side but the client receives a **generic** message;
crypto-library exceptions are caught and mapped to 422.

## Frontend

Reference views (Bootstrap, vanilla JS, overridable via `$views`):

- `webauthn_setup.php` — enrolment widget in the security overview: credential list
  (name, created, last used, delete) + "Add passkey" with a name field. JS:
  `register/options` → `create()` → `register/verify`.
- `webauthn_2fa_verify.php` — 2FA challenge page: triggers `get()` via `2fa/options`
  and posts the assertion to `auth/a/verify`.
- A "Sign in with a passkey" button added to the reference `login` view (conditional
  on `webauthnEnabled`): `login/options` → `get()` → `login/verify`.
- **base64url ↔ ArrayBuffer:** a shared JS partial (`_webauthn_js.php`) decodes
  `challenge`/`user.id`/`excludeCredentials[].id`/`allowCredentials[].id` before
  calling the API and encodes `rawId`/`response.*` before POSTing. Native
  `PublicKeyCredential.parseCreationOptionsFromJSON()`/`.toJSON()` is used where
  the browser supports it; the manual helper is the broad-compatibility baseline.
  Views carry the CI4 CSRF token; JS sends it in a header.

## Testing strategy

WebAuthn ceremonies need a real authenticator to produce valid
attestation/assertion — impossible in CI. Solution: a **test-only software
authenticator** (`Tests\Support\WebAuthn\VirtualAuthenticator`) that, given the
server-issued options, produces **real** attestation/assertion responses signed
with an in-test EC key pair. The server verifies them with the **real library** →
full end-to-end coverage of the verification path, no hardware, no brittle static
fixtures. The random challenge is pinned via the `ChallengeManager` test seam.
Reuse any `web-auth` test utilities where available.

Layers (TDD RED→GREEN):

- **Unit:** `ChallengeManager` (single-use, TTL, type/user binding);
  `WebAuthnCredentialModel`/`Repository` (CRUD, unique `credential_id`, revoked
  exclusion, source serialize/deserialize round-trip); entity; `HasWebAuthn`;
  `sign_count` anti-clone logic; `loginOptions` anti-enumeration.
- **Feature** (`DatabaseTestCase` + `FeatureTestTrait`): full enrolment
  (options→verify→row persisted); full passwordless login (→ session established);
  2FA flow (password→pending→2fa→logged in); credential deletion; **disabled ⇒ 404
  on every endpoint**; cap → 409; duplicate → 409.
- **Security-invariant tests:** one negative test per row above (tampered origin,
  replayed challenge, expired challenge, regressed counter, wrong-user credential in
  2FA, revoked credential, enumeration ⇒ identical responses).
- **Quality gates:** PHPStan level 5; deptrac (WebAuthn layering); php-cs-fixer;
  Rector dry-run. Lang keys added to all 19 locales + the
  `AbstractTranslationTestCase` excluded list.

## Risks & plan step 0

- **Dependency resolution (highest risk):** `web-auth/webauthn-lib` pulls symfony
  components that may conflict with a consumer's CI4 app. **Plan step 0:**
  `composer require web-auth/webauthn-lib`, confirm it resolves on a clean CI4
  install, register its services for PHPStan, and confirm PHPUnit/PHPStan stay green
  **before** building anything on top. If resolution is problematic, revisit the
  library choice with the user.
- **Exact lib API surface** (validator class names, repository interface) is pinned
  during step 0 against the installed version.
- **Browser/`navigator.credentials` differences** — the manual base64url helper is
  the baseline; native JSON serialization used opportunistically.

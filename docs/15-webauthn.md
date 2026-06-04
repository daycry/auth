# 🔑 WebAuthn / Passkeys

Passkeys let users authenticate with public-key cryptography instead of (or in addition to) a password — using **Face ID / Touch ID, Windows Hello, Android biometrics, or a hardware security key (YubiKey)**. The private key never leaves the device; the server stores only the public key. Passkeys are **phishing-resistant by design** (credentials are bound to your domain) and leave nothing replayable in the database.

This library supports **two integration models, both opt-in per user**:

- **Passwordless login** — sign in with just a passkey (usernameless / discoverable).
- **Second factor (2FA)** — present a passkey *after* the password, via the post-auth Action system (like TOTP).

## 📋 Table of Contents

- [Availability vs. Enforcement](#availability-vs-enforcement)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [Routes & JSON Endpoints](#routes--json-endpoints)
- [Enrollment](#enrollment)
- [Passwordless Login](#passwordless-login)
- [Passkey as a Second Factor](#passkey-as-a-second-factor)
- [HasWebAuthn Trait Reference](#haswebauthn-trait-reference)
- [Storage](#storage)
- [Frontend / JavaScript](#frontend--javascript)
- [Security Invariants](#security-invariants)
- [Testing](#testing)
- [Security Notes](#security-notes)

---

## Availability vs. Enforcement

Two **independent** axes — don't conflate them:

| Axis | Setting | Meaning |
|---|---|---|
| **Availability** | `AuthSecurity.$webauthnEnabled` (default `false`) | OFF ⇒ the feature **does not exist** — `auth()->routes()` registers no WebAuthn routes and every endpoint returns **404**. ON ⇒ users *may* enrol a passkey. |
| **Enforcement** | *(not in v1)* | Whether a passkey is *required* is a separate policy, intentionally out of scope for v1. Enabling availability never forces anyone. |

So: **enabling the flag lets users who want a passkey configure one. It never obligates anyone.**

---

## How It Works

WebAuthn replaces the shared secret (a password) with an asymmetric key pair generated and held by the authenticator. Authentication is a signed challenge–response, so there is no replayable secret and a database breach exposes only useless public keys.

**Passwordless login:**
```text
User clicks "Sign in with a passkey"
        ↓
Server issues a random challenge (request options)
        ↓
Browser → navigator.credentials.get() → device prompts biometric/PIN
        ↓
Authenticator signs the challenge with the private key
        ↓
Server verifies the signature against the stored public key,
checks origin / rpId / challenge / sign-count
        ↓
Session created — user is logged in
```

A passkey verified with *user verification* (biometric/PIN) is already multi-factor (possession + inherence), so passwordless login **completes the session directly** and does **not** re-run the `login` Action pipeline.

Under the hood this mirrors the **OAuth** pattern: a `WebAuthnManager` (in `src/Libraries/WebAuthn/`) orchestrates the ceremonies using [`web-auth/webauthn-lib`](https://github.com/web-auth/webauthn-framework) v5, a `WebAuthnCredentialRepository` maps rows ↔ the library's `CredentialRecord`, and a `WebAuthnController` exposes JSON endpoints. Verification ends in `auth()->login($user, false)`.

---

## Configuration

Enable the feature and tune the ceremony in `Config\AuthSecurity` (override via `setting()` at runtime):

| Setting | Default | Purpose |
|---|---|---|
| `$webauthnEnabled` | `false` | **Global availability flag.** |
| `$webauthnRelyingPartyId` | `null` → request host | The `rpId` — the domain credentials are bound to (anti-phishing). |
| `$webauthnRelyingPartyName` | `'Daycry Auth'` | Display name shown in the browser passkey prompt. |
| `$webauthnAllowedOrigins` | `[]` → derived from `base_url()` | Origins accepted during verification (add subdomains / native-app origins). |
| `$webauthnUserVerification` | `'preferred'` | `required` \| `preferred` \| `discouraged`. Use **`required`** for passwordless. |
| `$webauthnResidentKey` | `'preferred'` | Discoverable credential — needed for usernameless login. |
| `$webauthnAttestationConveyance` | `'none'` | `none` \| `indirect` \| `direct`. `none` is best for privacy. |
| `$webauthnAuthenticatorAttachment` | `null` | `null` (both) \| `platform` \| `cross-platform`. |
| `$webauthnTimeout` | `60000` | Ceremony timeout (ms). |
| `$webauthnChallengeTtl` | `120` | Challenge validity in seconds (single-use). |
| `$webauthnMaxCredentialsPerUser` | `10` | Per-user passkey cap. |

```php
// Recommended for true passwordless:
setting('AuthSecurity.webauthnEnabled', true);
setting('AuthSecurity.webauthnUserVerification', 'required');
setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
setting('AuthSecurity.webauthnRelyingPartyName', 'My App');
```

> **Dependency:** WebAuthn requires `web-auth/webauthn-lib:^5.3`. It is a normal Composer dependency of this library. Because this repo does not commit `composer.lock`, CI resolves a PHP-version-appropriate Symfony set per matrix row.

---

## Routes & JSON Endpoints

Registered automatically by `auth()->routes($routes)` **only when `webauthnEnabled` is true** (the controller also re-checks and 404s — defense in depth):

```text
POST  webauthn/register/options              # enrolment: get creation options   (auth required)
POST  webauthn/register/verify               # enrolment: verify attestation       (auth required)
POST  webauthn/login/options                 # passwordless: get request options   (public)
POST  webauthn/login/verify                  # passwordless: verify assertion        (public)
POST  webauthn/2fa/options                   # 2FA: request options for pending user
POST  webauthn/credentials/{uuid}/delete     # revoke a passkey                     (auth required)
```

All endpoints return JSON `{status, ...}` (or `{status:"error", error, message}` with a 4xx code). The browser-facing ceremony uses the bare option objects; the bundled JS wraps them as `{publicKey: ...}` before calling `navigator.credentials`.

---

## Enrollment

A logged-in user enrols a passkey from the security page. The bundled widget (`webauthn_setup` view) calls:

1. `POST webauthn/register/options {name?}` → `PublicKeyCredentialCreationOptions` (challenge stashed in the session, the user's existing credentials listed in `excludeCredentials`).
2. `navigator.credentials.create({publicKey})` → the device generates a key pair and signs the attestation.
3. `POST webauthn/register/verify {credential, name}` → the attestation is verified (origin, rpId, challenge), and the `CredentialRecord` is persisted. Returns `201 {status:"ok", credential:{uuid, name}}`.

Errors: `403` (not logged in), `409` (per-user cap reached or duplicate credential), `422` (verification failed), `404` (feature disabled).

---

## Passwordless Login

On the login page, a "Sign in with a passkey" button calls:

1. `POST webauthn/login/options {email?}` → request options. With **no email**, the flow is usernameless/discoverable (`allowCredentials` empty). With an email, it is scoped to that user's credentials. **Anti-enumeration:** an unknown email returns well-formed options with an empty `allowCredentials` and never reveals whether the account exists.
2. `navigator.credentials.get({publicKey})` → the device signs the assertion.
3. `POST webauthn/login/verify {credential}` → the credential is looked up by its id, the assertion is verified (signature, challenge, origin, rpId, user-verification, sign-count anti-clone), the counter is persisted, and the session is established. Returns `200 {status:"ok", redirect}`.

---

## Passkey as a Second Factor

Set the login action in `Config\Auth`:

```php
public array $actions = [
    'login' => \Daycry\Auth\Authentication\Actions\Webauthn2FA::class,
];
```

After a successful password login, `Webauthn2FA`:

- **Skips silently** if the user has no registered passkey (`createIdentity()` returns `''`).
- Otherwise inserts a pending marker and shows the `webauthn_2fa_verify` view, which requests an assertion scoped to the pending user and posts it to the Action verify endpoint.
- Applies the **same `UserLockoutManager` brute-force lockout** as password login, and enforces that the credential **belongs to the pending user**.

> Only one `login` action is supported at a time, so `Webauthn2FA` and `Totp2FA` are **mutually exclusive** as the login second factor in v1.

---

## HasWebAuthn Trait Reference

Mixed into the `User` entity:

| Method | Returns | Description |
|---|---|---|
| `webAuthnCredentials()` | `list<WebAuthnCredential>` | The user's active (non-revoked) passkeys. |
| `hasWebAuthnCredentials()` | `bool` | Whether the user has ≥1 active passkey. |
| `revokeWebAuthnCredential(string $uuid)` | `bool` | Soft-revokes a passkey the user owns. |

```php
$user = auth()->user();

if ($user->hasWebAuthnCredentials()) {
    foreach ($user->webAuthnCredentials() as $credential) {
        echo $credential->name, ' — last used: ', $credential->last_used_at;
    }
}
```

---

## Storage

Passkeys live in a dedicated `auth_webauthn_credentials` table (configurable via `Config\Auth::$tables['webauthn_credentials']`), following the same dedicated-table pattern as device sessions and TOTP backup codes:

| Column | Notes |
|---|---|
| `uuid` | UUID v7, external reference |
| `user_id` | FK → users (CASCADE) |
| `credential_id` | base64url credential id, **unique** — the assertion lookup key |
| `credential` | the serialized `Webauthn\CredentialRecord` (source of truth for all crypto) |
| `user_handle` | opaque WebAuthn handle = `users.uuid` (non-PII) |
| `name`, `sign_count`, `transports`, `aaguid` | denormalized for display / the anti-clone counter |
| `last_used_at`, `revoked_at` | usage + soft-revocation |

The `WebAuthnManager`, `WebAuthnCredentialRepository`, serializer and validators are resolvable, overridable services: `service('webAuthnManager')`, `service('webAuthnCredentialRepository')`, `service('webAuthnSerializer')`, `service('webAuthnAttestationValidator')`, `service('webAuthnAssertionValidator')`.

---

## Frontend / JavaScript

The library ships reference views (`webauthn_setup`, `webauthn_2fa_verify`) and a shared vanilla-JS partial (`_webauthn_js`) that handles the fiddly `base64url ↔ ArrayBuffer` conversions and the ceremony round-trips. They are overridable via `Config\Auth::$views`, and an SPA can ignore the views entirely and call the JSON endpoints directly. The credential list renders inside the existing `security_overview` page.

---

## Security Invariants

Every ceremony enforces (each has a dedicated negative test in `tests/WebAuthn/WebAuthnSecurityTest.php`):

1. Server-generated challenge (≥16 bytes), **single-use**, TTL-bounded, bound to ceremony type (and user for register/2FA).
2. **Origin binding** — `clientDataJSON.origin` must be an allowed origin (anti-phishing).
3. **rpId binding** — `rpIdHash` must match the configured `rpId`.
4. **User verification** enforced per `webauthnUserVerification`.
5. **Signature** verified against the stored COSE public key.
6. **Anti-clone** — the sign-count must advance; regressions are rejected (enforced by the library and the persisted counter).
7. **userHandle** must match the credential's stored handle.
8. **Ownership** — in 2FA / delete, the credential must belong to the (pending/logged-in) user.
9. **Revoked** credentials are excluded from lookup and `allowCredentials`.
10. **Anti-enumeration** — `login/options` never reveals whether an email exists.
11. **Lockout** — failed 2FA attempts feed the same per-user lockout as password failures.
12. **CSRF** — CI4 CSRF applies to the POST endpoints (the ceremony is additionally CSRF-resistant via the challenge).

---

## Testing

WebAuthn ceremonies normally need real hardware. This library ships a **test-only software authenticator** — `Tests\Support\WebAuthn\VirtualAuthenticator` — that produces *real* attestation/assertion responses (ES256, hand-built CBOR/COSE) which the genuine `web-auth/webauthn-lib` validators accept. Tests drive full ceremonies end-to-end without hardware and without brittle static fixtures:

```php
$authn   = new VirtualAuthenticator('example.com', 'https://example.com');
$options = service('webAuthnManager')->startRegistration($user, 'My Laptop');
$entity  = service('webAuthnManager')->finishRegistration($user, $authn->register(json_encode($options)));
```

> The library (v5.3) emits an `E_USER_DEPRECATED` for the still-required relying-party name. Because the test suite runs with `CODEIGNITER_SCREAM_DEPRECATIONS=1`, WebAuthn tests use the `Tests\Support\WebAuthn\SuppressesWebauthnDeprecations` trait to silence the library's own internal deprecations.

---

## Security Notes

- A passkey verified with **user verification** is multi-factor on its own; prefer `webauthnUserVerification = 'required'` for passwordless flows.
- Set an explicit `webauthnRelyingPartyId` (and `webauthnAllowedOrigins` for subdomains) in production — never rely on the request host for the rpId across multiple domains.
- The password remains a fallback unless you deliberately remove it; v1 does not implement passkey-only accounts.
- Keep `webauthnAttestationConveyance = 'none'` unless you specifically need authenticator-model attestation (which carries privacy and complexity costs).

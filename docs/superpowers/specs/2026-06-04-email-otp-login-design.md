# Email OTP Login (passwordless code) — design (2026-06-04)

Add a passwordless **email one-time-code login**, integrated into the existing
Magic Link flow as a second *delivery mode*. The user enters only their email,
receives a 6-digit code by email, enters it, and is logged in — no password, no
second factor. Implemented with TDD.

## Goal & scope

Magic Link becomes a passwordless **email login with two delivery modes**:

- **Link** *(existing)* — emailed a clickable magic link.
- **Code** *(new)* — emailed a 6-digit numeric code, entered in a form.

Both are pure passwordless logins for **existing users only** (no registration
changes). The login page offers whichever modes are enabled.

**Non-goals:** auto-registering unknown emails; using the code as a 2FA second
factor (it is a primary login); cross-device code entry (the code is bound to
the requesting session); making the code length configurable (fixed at 6, like
the Email2FA code).

## Decisions (approved)

| Decision | Choice | Rationale |
|---|---|---|
| Integration | Extend `MagicLinkController` with a `delivery` mode (link\|code) | One cohesive passwordless-email login; reuses the request flow, message/anti-enum, lockout, `TokenEmailSender`. |
| Anti-enumeration | **Unified** — both modes never reveal whether an email exists | Security + consistency. **Changes the existing Magic Link behaviour**: it no longer returns `invalidEmail` for unknown addresses (BC-safe at the API level; only the error message/flow changes). |
| Code-verify binding | **Session-bound** — the email is remembered server-side; the code form asks only for the digits | Binds the code to the requesting browser (anti-relay); standard OTP-login UX. |
| Code identity | New `IdentityType::MAGIC_CODE`, **hashed at rest**, **verified scoped to the user** | A 6-digit code is not globally unique — a global secret lookup could match another user's code. Scope the lookup by `user_id` + type. |
| Code length / expiry | 6 digits (fixed); expiry **configurable** via `AuthSecurity.$magicCodeLifetime` (default 10 min) | Matches Email2FA's code; codes are short-lived (shorter than links). |
| Brute-force protection | Reuse `UserLockoutManager` (per-user) on failed code attempts | Same mechanism as Email2FA/Totp2FA; mitigates guessing the 6-digit code. |

## Components

```
Modify:
  src/Controllers/MagicLinkController.php   # loginAction() branches on `delivery`; + codeView() + verifyCode()
  src/Libraries/TokenEmailSender.php        # + optional `?callable $tokenGenerator = null` (default: random_string('crypto', 20))
  src/Enums/IdentityType.php                # + case MAGIC_CODE = 'magic_code'
  src/Config/AuthSecurity.php               # + $magicLinkEnableLink, $magicLinkEnableCode, $magicCodeLifetime
  src/Config/Auth.php                       # + magic-link/code routes + $views keys
  src/Views/magic_link_form.php             # + two delivery buttons (link / code), gated by the enable flags
Create:
  src/Views/magic_link_code.php             # 6-digit entry form (+ resend)
  src/Views/Email/magic_link_code_email.php # email body containing the 6-digit code
```

`IdentityType::MAGIC_CODE = 'magic_code'` is distinct from `MAGIC_LINK` (looked
up globally by unique token) and `EMAIL_2FA` (a post-auth action). The code is
stored as `hash('sha256', code)` and verified by `hash_equals` against the
**user's own** `MAGIC_CODE` identity — never a global lookup.

## Configuration (`AuthSecurity`)

| Setting | Default | Purpose |
|---|---|---|
| `$allowMagicLinkLogins` *(exists)* | `true` | Master switch for the passwordless-email login (both modes). OFF ⇒ no routes / feature disabled. |
| `$magicLinkEnableLink` | `true` | Offer the **link** delivery mode. |
| `$magicLinkEnableCode` | `true` | Offer the **code** delivery mode. |
| `$magicCodeLifetime` | `10 * MINUTE` | Code expiry (configurable). Shorter than `$magicLinkLifetime` (`HOUR`). |

Both `enable` flags true ⇒ the form shows both buttons; only one ⇒ just that
mode; master off ⇒ the whole feature is gone. Code length is fixed at 6 digits
(`Utils::generateNumericCode(6)`).

## Routes

Extend the `magic-link` group in `Config\Auth::$routes`:

```
GET  login/magic-link          → loginView    (existing)  email + delivery buttons
POST login/magic-link          → loginAction  (existing → now branches on `delivery`)
GET  login/verify-magic-link   → verify       (existing)  link mode (stateless)
GET  login/magic-link/code     → codeView     (NEW)       6-digit form        [route: magic-link-code]
POST login/magic-link/code     → verifyCode   (NEW)
```

`$views`: add `magic-link-code` (`\Daycry\Auth\Views\magic_link_code`) and
`magic-link-code-email` (`\Daycry\Auth\Views\Email\magic_link_code_email`).

## Data flow (code mode)

1. **`loginView`** (GET) — email field + buttons for the enabled modes:
   "Email me a link" (`delivery=link`) / "Email me a code" (`delivery=code`).
2. **`loginAction`** (POST) — validate the email; look up the user.
   **Anti-enumeration:** regardless of existence, store the pending email in the
   (anonymous) session and proceed to the same destination. Only when the user
   exists is a code generated + emailed via
   `TokenEmailSender::sendTokenEmail($user, IdentityType::MAGIC_CODE, magicCodeLifetime, subject, magic-link-code-email, …, fn () => Utils::generateNumericCode(6))`.
   Redirect to `GET login/magic-link/code`. (The link mode keeps its existing
   behaviour but now also generic on unknown email.)
3. **`codeView`** (GET) — if no pending email in session ⇒ redirect to
   `magic-link`. Otherwise render the 6-digit form (posts to
   `login/magic-link/code`) plus a "resend code" control.
4. **`verifyCode`** (POST) — read the session email → user. Apply the
   `UserLockoutManager` lockout check. Fetch the user's `MAGIC_CODE` identity
   (scoped to `user_id` + type), compare `hash_equals($identity->secret,
   hash('sha256', $code))` and check expiry.
   - **Success:** delete the identity (single-use), `resetOnSuccess`, clear the
     session email; if a post-auth action is pending (`hasAction`) redirect to
     `auth-action-show`, otherwise `loginById` → establish session →
     `loginRedirect`. Record a successful login attempt.
   - **Failure / unknown email:** `recordFailedAttempt` (when a user exists), a
     **generic** "invalid or expired code" error, re-render the form. Record a
     failed login attempt.

`TokenEmailSender` gains an optional final parameter
`?callable $tokenGenerator = null` (defaults to `random_string('crypto', 20)`).
It already persists `hash('sha256', $token)`, so the code is hashed at rest
automatically; existing callers are unaffected (BC).

## Security invariants

Each has a dedicated test.

1. Code **hashed at rest** (SHA-256) — never stored in plaintext.
2. Verification **scoped to the user** (`user_id` + type), never a global secret
   lookup ⇒ one user's code can't authenticate another.
3. **Single-use** — the identity is deleted on a successful verify.
4. **Configurable expiry** (`magicCodeLifetime`, default 10 min).
5. **Session-bound** — the email lives in the anonymous session; the code is tied
   to the requesting browser.
6. **Anti-enumeration** — unknown email ⇒ identical flow + generic messages; no
   code created; generic "invalid/expired code" error. Also removes the existing
   Magic Link email-existence leak.
7. **Per-user lockout** — failed code attempts feed `UserLockoutManager` (as
   Email2FA/Totp2FA), defeating brute-force of the 6-digit code.
8. **CSRF** — CI4 CSRF on the POST forms.
9. Respects **pending post-auth actions** (`hasAction` → `auth-action-show`),
   like the link mode.
10. **Login attempts** recorded (`recordLoginAttempt`) on success and failure,
    like the link mode.
11. **Gating** — master `allowMagicLinkLogins` + per-mode flags; disabled modes
    are unavailable and their routes error/redirect.

## Testing

`DatabaseTestCase` + `FeatureTestTrait` (mirrors `MagicLinkControllerTest` /
`PasswordResetControllerTest`):

- **Request round-trip:** `POST login/magic-link {email, delivery=code}` for an
  existing user creates a hashed `MAGIC_CODE` identity and redirects to the code
  form.
- **Verify with a seeded known code** (PasswordReset pattern): seed a
  `MAGIC_CODE` identity with `secret = hash('sha256','123456')` + session email,
  then `POST login/magic-link/code {token:'123456'}` ⇒ logged in. Re-submitting
  the same code fails (single-use).
- **Expired code** rejected (seeded with past `expires`).
- **Wrong code** ⇒ generic error; **lockout** after the configured attempts.
- **Cross-user isolation** (invariant #2): two users sharing code `123456`; user
  A's session only ever logs in A.
- **Anti-enumeration:** unknown email ⇒ same flow (redirect to the code form, no
  leak) + generic errors; **and** the link mode no longer reveals `invalidEmail`.
- **Session-bound:** `GET login/magic-link/code` with no pending session email ⇒
  redirect to `magic-link`.
- **Mode gating:** `magicLinkEnableCode=false` ⇒ code mode unavailable;
  `magicLinkEnableLink=false` ⇒ link unavailable; master off ⇒ feature gone.
- **Pending action:** `hasAction` ⇒ redirect to `auth-action-show`.
- **Unit:** `TokenEmailSender` with the numeric generator (hashed 6-digit code,
  returns the raw); `IdentityType::MAGIC_CODE` value.

**Quality gates:** PHPStan level 5, deptrac, php-cs-fixer, Rector dry-run, and
the new lang keys added to all 19 locales (seeded in `en` order) + the
`AbstractTranslationTestCase` exclusion list. Docs: extend the Magic Link section
of `docs/03-authentication.md` with the code mode.

## New language keys (draft)

`magicCodeSubject`, `magicCodeEmailTitle`, `magicCodeTitle`, `magicCodePrompt`,
`magicCodeInvalid`, `magicCodeExpired`, `magicCodeResend`, `magicLinkSendLink`,
`magicLinkSendCode` (final set fixed during implementation).

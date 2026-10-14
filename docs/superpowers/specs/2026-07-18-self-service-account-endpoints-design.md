# Self-service account endpoints + security notifications — design (2026-07-18)

Add the five self-service account-management endpoints that `UserSecurityController`
and the docs already advertise but that do **not exist** in code, and close the
"security notification emails" gap by wiring a first-party notifier. Implemented
with TDD.

> This design was adversarially verified against the current tree (2026-07-18). The
> corrections from that pass are folded in below and flagged **[verified]**.

## Goal & scope

`UserSecurityController` currently exposes device-session, TOTP, login-activity
and password-confirmation endpoints. Its class docblock and `docs/05-controllers.md`
promise five more that are **not implemented**: `changePassword`, `changeEmail` +
`confirmEmailChange`, `unlinkOauth`, and (newly in scope) `deleteAccount`. This
work implements them, backed by two extracted services, and ships first-party
security-notification emails.

**In scope**

- `changePasswordView` / `changePassword`
- `changeEmailView` / `changeEmail` + `confirmEmailChangeView` / `confirmEmailChange`
- `unlinkOauth/$provider`
- `deleteAccountView` / `deleteAccount` (+ `confirmDeleteAccount` for password-less) — **anonymizes** (GDPR self-service)
- `Services\UserAnonymizer` — anonymization logic extracted from `GdprCommand`
- `Services\SecurityNotifier` — first-party security-notification email sender
- New procedural `src/Config/Events.php` with a default `suspicious-login` listener
  that delivers the (today orphaned) `suspicious_login_alert.php` template
- New email templates, form views, config, an `IdentityType::ACCOUNT_DELETE` case,
  and lang keys in all 19 locales

**Non-goals**

- Mandatory-MFA policy (separate roadmap item).
- Rate-limiting the new routes by default (routes stay manually registered by the
  app, matching every other `UserSecurityController` endpoint) — but the spec
  **mandates** CSRF coverage and recommends `rates` on these routes.
- Async/queued email delivery (the notifier is synchronous but swallow-and-log).
- A WebAuthn step-up reauth ceremony — password-less accounts reauth **by email**
  (approved decision), not by a passkey assertion.
- Hard-delete of the user row (we anonymize to preserve FK integrity).

## Approved decisions

| Decision | Choice | Rationale |
|---|---|---|
| Controller shape | Thin controller + extracted services | Mirrors `Session.php` delegating to focused services; avoids duplicating anonymize logic. |
| `deleteAccount` semantics | **Anonymize** via a shared `UserAnonymizer` service | Reuses the vetted `auth:gdpr anonymize` behavior; GDPR-aligned; preserves FK integrity. `GdprCommand` refactored to delegate. |
| **Password-less accounts** (OAuth-/passkey-only; `getEmailIdentity() === null`) | **Reauth by email.** `changePassword` becomes a *set-password* flow (session-authenticated, no current password); `changeEmail` skips the password check and relies on the new-address confirmation link + an alert to the current address; `deleteAccount` sends a confirmation link to the current email and anonymizes on click. | `getPasswordHash()` is nullable **[verified]** — a hard `current_password` requirement would lock these accounts out of all three flows. Email reauth covers every account type without building a WebAuthn step-up ceremony. |
| `deleteAccount` confirmation | **Immediate after reauth** (no extra double-opt-in for password accounts) | Approved. Password accounts: verify password → anonymize now. Password-less: the email link *is* the reauth. Farewell email is a notice, not a gate. |
| Security notifications | New `SecurityNotifier` service, `AuthSecurity::$securityNotificationEmails = true` by default | Closes roadmap gap #3. Every send is wrapped in try/catch + `log_message` so a mail failure never breaks the account action. |
| New-device / suspicious-login email | Default `Events::on('suspicious-login', …)` listener in a **new `src/Config/Events.php`** (NOT `Registrar`), gated on `$securityNotificationEmails` && `$suspiciousLoginAlerts` | **[verified]** `Registrar` only merges arrays into BaseConfig classes (Filters/Validation/Toolbar/Generators); CI4 Events have no registrar hook, so a listener there never fires. `Config/Events.php` is auto-discovered by `Events::initialize()`. |
| Email-change token | New `IdentityType::EMAIL_CHANGE` identity (already exists), **hashed at rest**, new email in `extra`, expiry `AuthSecurity::$emailChangeLifetime` (default `HOUR`) | Reuses `TokenEmailSender`'s hashed-token machinery; mirrors password-reset lifetime. |
| Confirm links are **POST-to-confirm** | `confirmEmailChange` / `confirmDeleteAccount` GET renders an interstitial with a POST form; the mutation happens on POST | **[verified critique]** A state-mutating GET is auto-fired by email scanners / prefetch and can't carry a CSRF token. Mirrors the existing `confirm_password` pattern. |
| `unlinkOauth` guard | Refuse if removal would leave **zero** login methods: password (`getEmailIdentity()`), another `oauth_*` identity, or a WebAuthn passkey (`hasWebAuthnCredentials()`) | **[verified]** Passkeys live in a separate table (not in `getIdentities()`), and `getIdentities()` returns all identity types unfiltered — the count must whitelist, not count rows. |
| Password-change side-effects | `PasswordChangeRecorder::record()` + terminate other device sessions + purge remember-me; keep current session | `record()` bumps `token_version` and stamps history; other devices are logged out. |

## Components

```
Create:
  src/Services/UserAnonymizer.php               # anonymize(User, string $initiator='cli', ?int $actorId=null): void
  src/Services/SecurityNotifier.php             # notify*(...) swallow-and-log; service('securityNotifier')
  src/Config/Events.php                         # procedural; default suspicious-login listener (auto-discovered)
  src/Views/security/change_password.php        # set/change password form
  src/Views/security/change_email.php           # new_email (+ current_password when the account has one)
  src/Views/security/confirm_email_change.php   # POST-to-confirm interstitial
  src/Views/security/delete_account.php         # reauth form (password) OR "send confirmation email" (password-less)
  src/Views/security/confirm_delete_account.php # POST-to-confirm interstitial (password-less path)
  src/Views/Email/password_changed_email.php    # notice → user
  src/Views/Email/email_change_requested_email.php # alert → OLD address, at REQUEST time
  src/Views/Email/email_changed_notice_email.php# notice → OLD address, at CONFIRM time
  src/Views/Email/account_delete_confirm_email.php # confirm link → user (password-less delete)
  src/Views/Email/account_deleted_email.php     # farewell → user (before anonymize)
  src/Views/Email/totp_disabled_email.php       # notice → user

Modify:
  src/Controllers/UserSecurityController.php    # + the new endpoints; totpDisable() → notify
  src/Commands/GdprCommand.php                  # anonymizeAction() delegates to service('userAnonymizer')
  src/Libraries/TokenEmailSender.php            # + ?string $to = null, array $identityExtra = [] APPENDED after $tokenGenerator
  src/Enums/IdentityType.php                    # + case ACCOUNT_DELETE = 'account_delete'
  src/Config/Auth.php                           # + $views keys for the new form views
  src/Config/AuthSecurity.php                   # + $emailChangeLifetime, $securityNotificationEmails
  src/Config/Services.php                       # + securityNotifier(), userAnonymizer() (BaseService pattern)
  src/Language/*/Auth.php                        # + deleteAccount*/notification*/set-password keys — ALL 19 locales
```

## Endpoint flows & guards

`hasPassword($user) := $user->getEmailIdentity() !== null` (the `email_password`
identity). All mutating POST routes MUST be behind CI4 CSRF (documented in the
route group); `rates` is recommended.

### changePassword (POST)
- **hasPassword**: require `current_password`, `new_password`, `new_password_confirm`.
  Verify current via `service('passwords')->verify($current, $user->getPasswordHash())` **[verified: bool]**.
- **password-less** (set-password): no `current_password`; the authenticated session is the auth.
- Validate the new password: `service('passwords')->check($new, $user)` **[verified: User arg mandatory]**;
  on failure read `$result->reason()`; success is `$result->isOK()` **[verified: no success()]**.
  (By default the pipeline is Composition/NothingPersonal/Dictionary; History + HIBP
  run only if added to `AuthSecurity::$passwordValidators` **[verified]**.)
- Capture `$previousHash`, save the new hash; `PasswordChangeRecorder::record($user, $previousHash)`
  (history, `password_changed_at`, `revokeIssuedTokens()`).
- Terminate other device sessions (`DeviceSessionModel::terminateAllForUser($user, session_id())`)
  and purge remember-me (`RememberModel::purgeRememberTokensByUserId($user->id)`); keep current session.
- Audit `EVENT_PASSWORD_CHANGED`; `SecurityNotifier::passwordChanged($user)`; `Events::trigger('password-changed', $user)`.

### changeEmail (POST)
1. `new_email` required; `current_password` required **only if** `hasPassword` (verified then).
2. Early return if `strtolower($new) === strtolower($user->email)` (no-op message).
3. Uniqueness: reject `changeEmailAlreadyUsed` if `$new` belongs to **another** account
   (exclude the user's own identity). *Note: this is an authenticated-only, low-severity
   enumeration surface — mitigated by session-auth + CSRF + `rates`; documented as such.*
4. `TokenEmailSender::sendTokenEmail($user, EMAIL_CHANGE, $emailChangeLifetime, changeEmailSubject,
   email_change view, extraData, tokenGenerator: null, to: $new, identityExtra: ['new_email' => $new])`
   **[verified: params appended after $tokenGenerator; identityExtra json_encoded into `extra`; recipient = `$to ?? $user->email`]**.
5. `SecurityNotifier::emailChangeRequested($user)` → alert the **current** address now **[verified critique: alert at request time, not only confirm]**.
6. Audit `EVENT_EMAIL_CHANGE_REQUEST`; redirect with `changeEmailSent` (generic — never reveals delivery).

### confirmEmailChange (GET ?token=) → interstitial; confirmEmailChange (POST) → mutate
- **GET**: validate the `EMAIL_CHANGE` identity by hashed token; invalid → `changeEmailTokenInvalid`,
  expired → `changeEmailTokenExpired`; else render `confirm_email_change` with a POST form carrying the token.
- **POST**: re-validate token (single-use), re-check the target still free, then
  `$user->setEmail($new)` on the **entity** and `UserModel::save($user)` **[verified critique: entity, not array — the afterUpdate `saveEmailIdentity` callback early-returns for array updates, so the login identity would not sync]**; delete the `EMAIL_CHANGE` identity (consume-then-act; handle the unique-constraint race).
- Audit `EVENT_EMAIL_CHANGED`; `SecurityNotifier::emailChanged($user, $oldEmail)` → notice to the OLD address; `Events::trigger('email-changed', $user, $oldEmail)`; redirect `changeEmailSuccess`.

### unlinkOauth/$provider (POST)
1. Resolve `oauth_<provider>` via `IdentityType::oauthProvider($provider)`; missing → `unlinkOauthNotFound`.
2. Compute remaining login methods **[verified]**: `hasPassword = getEmailIdentity() !== null`;
   `remainingOauth = array_filter($user->getIdentities(), fn($i) => str_starts_with($i->type,'oauth_') && $i->type !== IdentityType::oauthProvider($provider))`;
   `hasPasskey = $user->hasWebAuthnCredentials()`. If all three are falsy → `unlinkOauthLastMethod` (refuse).
3. Delete the identity; audit `EVENT_OAUTH_UNLINKED`; `Events::trigger('oauth-unlinked', $user, $provider)`; redirect `unlinkOauthSuccess`.

### deleteAccount (POST) + confirmDeleteAccount (password-less)
- **hasPassword**: require + verify `current_password` → `SecurityNotifier::accountDeleted($user)` (farewell, before anonymize) → `auth()->logout()` → `service('userAnonymizer')->anonymize($user, 'self-service', $user->id)` → redirect home `deleteAccountSuccess`.
- **password-less**: POST issues an `ACCOUNT_DELETE` email token (via `TokenEmailSender`) to the current
  email and shows `deleteAccountEmailSent`. `confirmDeleteAccount` GET renders an interstitial; its POST
  validates the token (single-use), then runs the same farewell → logout → anonymize sequence.

## Services

### UserAnonymizer  **[verified: extract with adjustments, not verbatim]**
`anonymize(User $user, string $initiator = 'cli', ?int $actorId = null): void`. Moves the
mutation block from `GdprCommand::anonymizeAction()` (lines 161-197): delete all rows in
`UserIdentityModel`, `DeviceSessionModel`, `TotpBackupCodeModel`, `PasswordHistoryModel` for the
user; overwrite the user row (`username='deleted_'.$id, active=0, failed_login_count=0,
locked_until=null, password_changed_at=null`); write `EVENT_USER_ANONYMIZED` with
`['initiator' => $initiator]` (**parameterized** — the CLI passes `'cli'`, the endpoint
`'self-service'`). Resolves `model(UserModel::class)` itself (the current param is dropped).
**Wraps the four deletes + the update in a DB transaction** (a robustness improvement over the
current non-transactional command — approved as part of the extraction). Throws on failure;
`GdprCommand` keeps its prompt / success-output / try-catch → error-exit wrapper and delegates the
mutation. Email is anonymized by deleting the `EMAIL_PASSWORD` identity, not by overwriting a column.

### SecurityNotifier
Each public method builds a CI4 `Email`, renders the matching `Views/Email/*` template, and is
wrapped so a failure is logged and swallowed. Methods: `passwordChanged(User)`,
`emailChangeRequested(User)`, `emailChanged(User, string $oldEmail)`, `accountDeleted(User)`,
`totpDisabled(User)`, `suspiciousLogin(User, array $flags, string $ip, string $ua)`. All gated on
`AuthSecurity::$securityNotificationEmails`. Registered as `service('securityNotifier')`
(BaseService pattern **[verified]**) so apps can swap it — advances the "injectable email service" item.

## Configuration (`AuthSecurity`)

| Setting | Default | Purpose |
|---|---|---|
| `$emailChangeLifetime` | `HOUR` | Confirmation-link expiry for email/delete tokens (mirrors `$passwordResetLifetime`). |
| `$securityNotificationEmails` | `true` | Master switch for first-party security-notification emails. |

The suspicious-login email additionally requires the existing `$suspiciousLoginAlerts = true`.

## Language keys  **[verified]**

The `changePassword*/changeEmail*/unlinkOauth*` keys already exist in **all 19 locales**
(English placeholders in the 18 non-`en`, permitted by the `$excludedKeyTranslations` allow-list
in `tests/Language/AbstractTranslationTestCase.php`; the `lang` suite enforces parity). **Zero
existence backfill.** New keys — `deleteAccount*`, `setPassword*`, the notification subjects/bodies,
`deleteAccountEmailSent` — must be added to all 19 locales and registered in the allow-list so the
parity suite passes. Real translations are out of scope (placeholders, consistent with the codebase).

## Testing (TDD)

Feature tests (`DatabaseTestCase`), per endpoint + per service. Beyond the happy paths:
- **changePassword**: password account (wrong current, weak/rejected new via an explicitly-enabled
  validator, mismatch); **password-less set-password** (no current required); current session survives
  while others are terminated; `token_version` bumped; notify on/off.
- **changeEmail/confirm**: end-to-end; `new_email == current` no-op; already-in-use (excludes own
  identity) at request and confirm; token invalid/expired; single-use (second POST fails); confirm
  saves the **entity** so login works with the new address; alert to OLD address at request AND confirm;
  password-less path needs no current_password.
- **unlinkOauth**: happy path; not linked; last-method refusal when password removed and only OAuth left;
  passkey counts as a method (separate table).
- **deleteAccount**: password path (immediate); password-less email-confirm path; wrong password refused;
  identities gone + row anonymized + audit `initiator='self-service'`; farewell sent before anonymize;
  other sessions invalidated (identity deletion + `active=0`).
- **UserAnonymizer**: parity with the pre-extraction CLI end state; transaction rolls back on a mid-sequence failure.
- **SecurityNotifier**: sends when enabled, silent when off, swallows a thrown mail error.
- **Events.php listener**: `suspicious-login` triggers a send when both flags on (proves it is wired, unlike a Registrar no-op).

Email assertions use CI4's mock email, matching existing email tests.

## Docs & delivery

- Rewrite the `UserSecurityController` section of `docs/05-controllers.md` (it documents
  non-existent methods) with the real method set, the new endpoints, the canonical CSRF-protected
  route group, and the password-less behavior.
- Add the notification settings + `deleteAccount` to `docs/13-audit-and-compliance.md` and the config reference.
- CHANGELOG `[Unreleased]` under Added / Compliance.
- Full local gate (`composer ci`), `mkdocs build --strict`, lang parity. Regenerate the PHPStan
  baseline only if a real CI4-plugin flag appears — never hand-edit it.
- PR to `development` (never push to it directly).

## Verification ledger (resolved)

| # | Assumption | Verdict | Resolution in this spec |
|---|---|---|---|
| 1 | `passwords` API | PARTIAL | `check($pw, $user)` User mandatory; `isOK()`/`reason()`; default pipeline is 3 validators. |
| 2 | Anonymize extractable | PARTIAL | Extracted with `initiator` param, self-resolved `UserModel`, wrapped in a transaction. |
| 3 | Lang keys exist | PARTIAL | Already in all 19 locales; only new keys added. |
| 4 | Identity/passkey enumeration | PARTIAL | Guard whitelists password + `oauth_*` + `hasWebAuthnCredentials()` across two tables. |
| 5 | `TokenEmailSender` extensible | PARTIAL | Params appended after `$tokenGenerator`; `extra` json_encoded; `setTo($to ?? user->email)`. |
| 6 | Listener home | **WRONG** | Moved from `Registrar` to a new procedural `src/Config/Events.php`. |
| — | Audit constants / session helpers | CLEARED | All referenced constants + `terminateAllForUser`/`purgeRememberTokensByUserId`/`setEmail` exist as assumed. |

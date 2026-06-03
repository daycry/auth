# Auth — refactors & features design (2026-06-02)

Follow-up to the security-audit roadmap. 12 items, implemented with TDD, no
heavy-dependency changes except WebAuthn (approved). No commits until requested.

## Refactors

### #2 — Safe `auth()` facade
`Auth::__call()` throws `BadMethodCallException` when the resolved authenticator
lacks the method (instead of returning `null`). Fix the drifted `@method`
docblock. Add a test asserting every documented `@method` exists on
Base/Session/StatelessAuthenticator.

### #5 — Split `Session::login()`
Extract a private `forceLoginWithoutActions()` (used by `loginById`) and a shared
tail helper; remove the duplicated `startLogin()/issueRememberMeToken()`. Public
API unchanged.

### #4 — Remove `__destruct()` side effects
Wrap `BaseControllerTrait::__destruct()` request-logging / attempt-handling in
`try/catch` (kills the uncatchable shutdown fatal, no BC break) and guard against
double execution. Optionally expose an `after` filter as the recommended path.

### #3 — Repository seam
`JwtController` goes through `JwtTokenRepository`. The three token repositories
become resolvable services (`service('jwtTokenRepository')`, etc.) so apps can
override them; internal `new XRepository()` call sites use the service.

### #6 — Gate → RBAC bridge
`Gate` falls back to `$user->can($ability)` when the ability contains `.` and no
closure/policy matches. Config `AuthSecurity.gateFallbackToRbac` (default `true`).

### #1 — Decompose `Authorizable` god-trait
New `Authorization\GroupPermissionRepository` owns group/permission loading,
persistent cache, transactional persistence and audit-logging. `Authorizable`
becomes a thin façade delegating to it; per-request in-memory cache stays on the
trait. `User` public API unchanged (BC). Removes the Entity→DB layering inversion
and the deptrac whitelist. Existing authz tests must stay green.

## Features

### C — Remember-me theft detection
On selector-match / validator-mismatch: purge all of the user's remember tokens,
write an `AuditLogger` event, fire a `suspicious-login` event.

### D — TOTP anti-replay
`TOTP::verifyAndGetTimestep()` returns the matched time-step (keep `verify()`).
Store the last-accepted time-step in the TOTP identity `extra` JSON; reject codes
whose time-step `<=` the last used.

### E — Sudo mode (step-up with TTL)
Extend `PasswordConfirmFilter`: after re-confirmation store `auth_sudo_until` in
session; pass while within `AuthSecurity.sudoModeLifetime` (default 600s), else
re-challenge.

### A — JWT access-token revocation
Add `token_version` (int, default 0) to `users` (migration). Adapter encodes
payload `{uid, tv}`; JWT authenticator reads `uid`, rejects when `tv` !=
user's `token_version`. Scalar payload (legacy token) → treated as `uid`, `tv`
check skipped. `logout`/`ban`/password-change bump `token_version`. Shorten the
default access-token lifetime + document.

### F — Social linking for logged-in user
`OauthController::link($provider)` — authenticated user initiates linking; the
callback in "linking mode" links to the current user (no email-merge). Unlink
already exists.

### B — WebAuthn / Passkeys
`require web-auth/webauthn-lib`. Migration: `auth_webauthn_credentials`
(+ challenge in session). `WebAuthnController` with 4 endpoints
(register-options / register-verify / login-options / login-verify), server-side
ceremony with TDD. New `IdentityType::WEBAUTHN`. Example view + JS
(`navigator.credentials`) — not headless-testable.

## Execution order (ascending risk, all TDD)
1. Small/safe: #2, #5, #4, C, D
2. Medium: #3, #6, E, A, F
3. Large: #1 (god-trait), B (WebAuthn)
4. Final: PHPStan + deptrac + full suite + baseline regen if needed

## Decisions taken
- #4: try/catch + opt-in filter.
- #6: `gateFallbackToRbac` default true.
- D: keep `verify()`, add `verifyAndGetTimestep()`.
- A: payload `{uid, tv}` with scalar back-compat.

---
hide:
  - navigation
  - toc
---

<div class="hero" markdown>

# Daycry Auth

Authentication &amp; Authorization for **CodeIgniter 4** — Session, Access Token, JWT, OAuth, TOTP and **WebAuthn / Passkeys**, with a full **RBAC** authorization system. Batteries included, secure by default.

[Get started :material-rocket-launch:](01-quick-start.md){ .md-button .md-button--primary }
[WebAuthn / Passkeys :material-fingerprint:](15-webauthn.md){ .md-button }
[GitHub :material-github:](https://github.com/daycry/auth){ .md-button }

</div>

## Features

<div class="grid cards" markdown>

-   :material-key-variant:{ .lg .middle } __Multiple authenticators__

    ---

    Session, Access Token (with scope enforcement), JWT (refresh tokens + one-shot **revocation** via `token_version`), and Magic Link (email link or **6-digit code**) — all behind one helper.

    [:octicons-arrow-right-24: Authentication](03-authentication.md)

-   :material-fingerprint:{ .lg .middle } __WebAuthn / Passkeys__

    ---

    **Passwordless login** (usernameless/discoverable) and **passkey 2FA**. Phishing-resistant by design, opt-in per user behind a global flag.

    [:octicons-arrow-right-24: WebAuthn](15-webauthn.md)

-   :material-cellphone-key:{ .lg .middle } __TOTP two-factor__

    ---

    RFC 6238 TOTP with **backup codes**, *"trust this device"* bypass, per-user brute-force lockout, and **single-use anti-replay** codes.

    [:octicons-arrow-right-24: TOTP 2FA](10-totp-2fa.md)

-   :material-account-multiple-check:{ .lg .middle } __OAuth 2.0 / Social__

    ---

    Google, GitHub, Facebook, Microsoft Azure and any OIDC provider. Profile fields, OAuth events, explicit **account linking** and verified-email merge safety.

    [:octicons-arrow-right-24: OAuth](09-oauth.md)

-   :material-shield-account:{ .lg .middle } __RBAC authorization__

    ---

    Groups &amp; permissions with optional cache, uniform wildcard matching (`posts.*`), and a **Gate → RBAC bridge**. Plus filters: group, permission, gate, token-scope.

    [:octicons-arrow-right-24: Authorization](06-authorization.md)

-   :material-devices:{ .lg .middle } __Device sessions__

    ---

    Track active logins per device, optional concurrent-session limit, and **real enforced revocation** — a revoked session must re-authenticate on its next request.

    [:octicons-arrow-right-24: Device Sessions](11-device-sessions.md)

-   :material-speedometer:{ .lg .middle } __Filters &amp; rate limiting__

    ---

    Per-route rate limits (`rates:<limit>,<period>`) and **sudo mode** (`password-confirm:<seconds>`) that override global windows on your most sensitive routes.

    [:octicons-arrow-right-24: Filters](04-filters.md)

-   :material-clipboard-text-clock:{ .lg .middle } __Audit &amp; compliance__

    ---

    Granular **audit log** (22 event types), **GDPR** export/anonymize helpers, and an admin **CLI** for tokens, sessions, TOTP, audit and scheduled purges.

    [:octicons-arrow-right-24: Audit &amp; Compliance](13-audit-and-compliance.md)

</div>

## Quick start

=== ":material-package-down: Install"

    ```bash
    composer require daycry/auth
    php spark migrate --all
    php spark auth:setup
    ```

=== ":material-login: Authenticate"

    ```php
    $result = auth()->attempt([
        'email'    => 'user@example.com',
        'password' => 'secret',
    ]);

    if ($result->isOK()) {
        return redirect()->to('/dashboard');
    }
    ```

=== ":material-shield-lock: Protect a route"

    ```php
    // app/Config/Routes.php
    $routes->group('admin', ['filter' => 'group:admin'], static function ($routes) {
        $routes->get('dashboard', 'Admin::index');
    });
    ```

[:octicons-arrow-right-24: Full quick-start guide](01-quick-start.md)

## Security, by default

<div class="grid cards" markdown>

-   :material-lock-check:{ .lg .middle } __Hardened auth__

    ---

    Per-user atomic lockout, compromised-password recheck (HIBP), suspicious-login &amp; remember-me theft detection, and a **secret-safe login log** (SHA-256 fingerprints, never raw tokens).

-   :material-puzzle:{ .lg .middle } __Customizable__

    ---

    Swap or extend any component — authenticators, repositories, views, actions and policies are all resolvable services you can override.

    [:octicons-arrow-right-24: Configuration](02-configuration.md)

-   :material-test-tube:{ .lg .middle } __Tested__

    ---

    A large PHPUnit suite (incl. a real in-test WebAuthn authenticator), PHPStan level 5, deptrac and Rector keep the library correct and clean.

    [:octicons-arrow-right-24: Testing](08-testing.md)

</div>

---

<p class="md-content-footer" markdown>
**Resources** —
[GitHub](https://github.com/daycry/auth) ·
[Packagist](https://packagist.org/packages/daycry/auth) ·
[Issues](https://github.com/daycry/auth/issues) ·
[CodeIgniter 4](https://codeigniter4.github.io/)
</p>

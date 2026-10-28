# Authentication hardening

## Generated routes and rate limits

`auth()->routes($routes)` applies `rates:auth` to its generated routes. This mode
uses an independent bucket for each route, HTTP method and client network. It
does not put every anonymous visitor in one shared bucket. The defaults remain
`AuthSecurity::$requestLimit = 10` and `$timeLimit = 60` seconds.

Client IPs come from CodeIgniter's request service; configure trusted proxies in
the host application. IPv6 privacy addresses in one `/64` share a network key.
IPv4 addresses remain separate. `rates` also supports `USER_IP` as the global
`limitMethod` for a normalized submitted email plus client network. Generated
routes deliberately use the network mode so rotating account names cannot
evade their limit. Rejections return HTTP 429 and `Retry-After`.

```php
// Override one group; the other groups retain their configured defaults.
auth()->routes($routes, ['routeFilters' => ['login' => 'rates:5,MINUTE']]);

// An application with its own equivalent limiter can opt out explicitly.
auth()->routes($routes, ['routeFilters' => ['login' => false]]);
// Or disable the generated defaults for every group:
auth()->routes($routes, ['routeFilters' => false]);
```

Custom filters such as `rates:5,MINUTE` use the configured `limitMethod`. Keep
that method client-specific when limiting public routes. `Auth::$routeFilters`
provides the same mapping at configuration level. CSRF protection remains an
application filter and must protect browser session mutations.

## OAuth authorization-code flow

Configured providers use PKCE S256 by default. The verifier stays in the browser
session and is restored on the separate callback request. The state and verifier
are consumed before a token exchange, including failed exchanges. A flow is
bound to its provider and expires after ten minutes.

For a provider that does not support S256, explicitly set `'pkce' => false` in
that provider's `AuthOAuth::$providers` entry. This changes the security of that
provider's flow; prefer a provider that supports PKCE.

Providers supporting authorization-response issuer identification can set:

```php
'issuer' => 'https://identity.example.com',
```

The callback must then include an exact matching `iss`. Missing or mismatched
issuers are rejected before exchanging the authorization code. Use the exact
issuer for the configured tenant, including any trailing slash.

For an OpenID Connect provider, also set `'oidc' => true`. This requests a random
nonce and checks the ID-token issuer, audience, authorized party, expiry,
issued-at time, subject and nonce. Its subject must agree with the returned
resource owner's subject. This option requires an HTTPS token endpoint, an
`issuer` and `clientId`, and a provider whose resource owner exposes its OIDC
subject. These checks apply only to ID tokens received directly from the trusted
token endpoint in the authorization-code flow. Browser-supplied ID tokens and
implicit/hybrid flows are not supported.

The implementation follows [League's PKCE session guidance](https://oauth2-client.thephpleague.com/usage/),
[RFC 9207 issuer validation](https://www.rfc-editor.org/rfc/rfc9207.html#section-2.4)
and the direct TLS token-endpoint validation option in
[OpenID Connect Core 3.1.3.7](https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation).
It is not a general-purpose validator for arbitrary signed ID tokens.

## Refresh-token families

Run the `2026-10-28-000001` migration before deploying the new code. It adds a
nullable `refresh_family` field and lookup index to the configured identities
table. Existing tokens are assigned a family when first rotated; no raw secrets
are backfilled or stored.

Each JWT login starts a separate family. Refresh consumes the predecessor with a
conditional database update and inserts its successor in one transaction. Only
one request can consume that predecessor. Reusing a known consumed token returns
401, revokes the family's successors and records a revocation audit event with
reason `reuse`. Other login families are preserved. Unknown tokens cannot revoke
another family. Clients must serialize refresh requests and replace their stored
refresh token after each success; concurrent refreshes are treated as replay.

Family revocation stops further refreshes. Access tokens already issued in that
family keep their normal short lifetime unless the user's token version is also
revoked. Ban and password changes retain their existing user-wide revocation.
Keep consumed records until their replay-detection window is no longer needed.

The conditional-update/rollback behavior is covered with SQLite integration
tests. Production deployments should exercise concurrent refreshes on their
transactional database engine; SQLite tests do not establish MySQL/PostgreSQL
locking behavior or migration lock duration.

## JWT with mandatory MFA

A password-only JWT login is rejected with HTTP 403 when the user's MFA policy
requires another factor. Submit `mfa_code` with a current TOTP or unused backup
code, alongside the email and password. Invalid codes feed the per-user lockout
counter; valid codes retain anti-replay/single-use behavior. Passkey users can
also use a currently authenticated session with a verified second-factor proof
for that same user; a proof without the matching live session is insufficient.

Successful strong grants mark the refresh family and access-token payload with
MFA assurance. Rotation preserves this assurance. If mandatory MFA is enabled
later, old password-only refresh and access tokens are rejected. Neither a scalar
legacy JWT nor a string such as `"true"` counts as an MFA proof. Errors distinguish
`mfa_required` and `mfa_enrollment_required`.

## Session lifetimes

The following `Auth::$sessionConfig` options are seconds; both default to `0`
(disabled) for compatibility:

```php
'idleTimeout' => 30 * MINUTE,
'absoluteTimeout' => 24 * HOUR,
```

Successful authenticated requests update activity without moving the original
start time. When either enabled deadline expires, authentication and MFA/step-up
proofs are cleared, the device session is terminated, and the current remember-me
token is revoked. It cannot silently sign the user back in after that expiry.
Remember-me restoration also checks the original token issue time against the
absolute limit; rotating the cookie does not restart the lifetime.

Sessions created before this release acquire timestamps on their first request
under the new code. This gives those existing sessions at most one configured
absolute window from that request. Applications wanting an immediate rollout
must invalidate their existing sessions explicitly.

Retiring a remember-me cookie also removes its matching server token after
checking the validator; supplying a selector without its secret cannot revoke a
different device. `Auth::$redirects['force_reset']` now points to the supplied
`auth/force-reset` form instead of the application's home page.

# Mandatory MFA, step-up and recovery codes

Mandatory MFA is disabled by default. It applies automatically to session
requests protected by `auth:session`, a session match in `chain`, or standalone
group, permission and gate filters; applications can also use the
`mfa-required` filter after session authentication. API keys remain a separate
authentication method: do not use a session-only MFA policy as an API scope.

```php
// app/Config/AuthSecurity.php
public bool $mfaRequired = false;
public array $mfaRequiredGroups = ['admin'];
public array $mfaExemptGroups = ['service-account'];
public int $mfaGracePeriod = 7 * DAY;
public int $mfaEnforcementStartedAt = 1793145600; // fixed rollout timestamp
```

Exempt groups take precedence. Grace runs from the later of account creation
and the configured rollout timestamp. Keep that timestamp fixed across
deployments; it is never restarted by logging out, changing browser or clearing
cookies. With no grace, existing accounts must enroll before accessing
protected application routes.

The policy requires a confirmed TOTP secret or an active passkey while WebAuthn
is enabled. A password alone cannot satisfy it. Merely owning a factor is also
insufficient: the current session needs a successful factor verification. A
new login and remember-me restoration do not inherit another login's proof.
Trusted-device shortcuts do not replace the factor proof required by this
policy.

Unenrolled users can reach the security overview, first-factor enrollment,
confirmation and logout routes. An already enrolled user must prove an
existing factor before registering an additional one. Policy enforcement does
not allow disabling the last required factor. JSON clients receive HTTP 403
with `code` equal to `mfa_enrollment_required` or `mfa_verification_required`,
and a `redirect` to the next step.

Enrolling the first mandatory factor also requires a recent interactive
sign-in or a recent confirmation bound to the current user. A remember-me
cookie alone cannot enroll a new factor. Password-less users with an old
remembered session must sign in interactively through their existing provider
before enrollment.

## Confirming sensitive actions

The existing `auth/confirm-password` form supports password, TOTP and passkey
confirmation. Applications retain the `password-confirm` filter on sensitive
routes. The confirmation timestamp is bound to the current user; future dates
and another user's proof are refused. TOTP/passkey confirmation additionally
satisfies mandatory MFA. Password confirmation does not.

Passkey confirmation starts at `POST auth/confirm-password/options` and ends
at `POST auth/confirm-password` with `factor=passkey` and the serialized
`credential`. The challenge is single-use, requires user verification and is
bound to the signed-in user. A login or another user's challenge cannot be
substituted. TOTP uses `factor=totp` and `token`. Password uses
`factor=password` and `password`. Failures share the account lockout policy.
Keep CSRF and rate limiting enabled on these routes.

JSON bodies are supported too. Successful confirmation returns HTTP 200 with
`status: success`, `confirmed: true` and `redirect`; invalid proof returns 422,
and an account lock returns 429. Passwords, codes and credentials belong in the
request body, never in query parameters.

## Recovery codes

`GET account/security/recovery-codes` displays a regeneration form. Its POST
requires recent user-bound confirmation and replaces every previous code. The
new codes appear only in that response, which is marked `Cache-Control:
no-store`; only hashes are stored. No GET regenerates or retrieves secrets.
The link is present in the security overview when these routes are registered.

Passkey-only 2FA users can generate the same one-time recovery codes as TOTP
users. The passkey 2FA screen accepts `recovery_code`; one successful use consumes
the code atomically and completes the second-factor action. It remains scoped
to the pending user and subject to lockout. After recovery, enroll a replacement
factor and regenerate the remaining codes.

## Replacing the policy

Implement `Daycry\Auth\Interfaces\MfaPolicyInterface` and override the
`mfaPolicy` service. Enrollment, group exemptions, grace and session proof
validation then use that implementation. Preserve user binding and clear proof
on authentication-session changes. See [Extending the core](16-extending.md).

Recovery codes can also satisfy the mandatory-MFA confirmation flow when no
login action is configured. Select the recovery-code form on the confirmation
page, or send `factor=recovery` with `token`. A successful proof consumes the
code once and records a user-bound MFA and step-up proof. Failed attempts use
the same account lockout policy.

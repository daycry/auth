# 🖥️ CLI Commands

Daycry Auth ships several Spark commands for setup, user management, and operational/admin tasks. All live under the `Auth` group:

```bash
php spark list Auth
```

## 📋 Index

- [Setup & Discovery](#setup--discovery)
  - [`auth:setup`](#auth-setup)
  - [`auth:discover`](#auth-discover)
- [User management](#user-management)
  - [`auth:user`](#auth-user)
- [Token & session admin](#token--session-admin)
  - [`auth:tokens`](#auth-tokens)
  - [`auth:sessions`](#auth-sessions)
- [Maintenance](#maintenance)
  - [`auth:purge`](#auth-purge)
- [Two-factor admin](#two-factor-admin)
  - [`auth:totp`](#auth-totp)
- [Audit & compliance](#audit--compliance)
  - [`auth:audit`](#auth-audit)
  - [`auth:gdpr`](#auth-gdpr)

---

## Setup & Discovery

### `auth:setup`

Bootstraps a fresh installation: copies `Config/Auth.php` into `app/Config/`, registers the routes, sets `csrfProtection = 'session'`, configures email defaults, and runs migrations.

```bash
# Interactive
php spark auth:setup

# Force overwrite of existing app/Config/* files
php spark auth:setup -f
```

> Run once after `composer require daycry/auth`. Idempotent — safe to re-run when upgrading.

### `auth:discover`

Walks the application's controllers and registers them in the auth tables (used by the per-controller permission system). Run this any time you add or rename controllers if you rely on the database-backed authorization model.

```bash
php spark auth:discover
```

---

## User management

### `auth:user`

Create / update / inspect users from the CLI.

```bash
# Create a user (prompts for password)
php spark auth:user create -n alice -e alice@example.com

# Activate / deactivate
php spark auth:user activate   -e alice@example.com
php spark auth:user deactivate -e alice@example.com

# Rename
php spark auth:user changename  -e alice@example.com --new-name alice_doe

# Change email
php spark auth:user changeemail -e alice@example.com --new-email alice@new.org

# Delete
php spark auth:user delete -e alice@example.com

# Reset password (prompts)
php spark auth:user password -e alice@example.com

# List
php spark auth:user list
php spark auth:user list -e alice@example.com

# Manage groups
php spark auth:user addgroup    -e alice@example.com -g admin
php spark auth:user removegroup -e alice@example.com -g admin
```

> For GDPR-compliant deletion that preserves foreign-key integrity, prefer [`auth:gdpr anonymize`](#auth-gdpr) over `auth:user delete`.

---

## Token & session admin

### `auth:tokens`

Soft-revokes a user's API tokens. Soft-revocation sets `revoked_at` so the row is filtered out on lookup but remains for audit purposes.

```bash
# All tokens (access + JWT refresh)
php spark auth:tokens revoke -e alice@example.com

# Just personal access tokens
php spark auth:tokens revoke -e alice@example.com --type=access_token

# Just JWT refresh tokens
php spark auth:tokens revoke -e alice@example.com --type=jwt_refresh

# By user id
php spark auth:tokens revoke -i 42 --type=all
```

| Option | Description |
|--------|-------------|
| `-e <email>` | Target user by email (alternative to `-i`). |
| `-i <id>` | Target user by id. |
| `--type` | `access_token`, `jwt_refresh`, or `all` (default). |

Each successful revocation writes an `EVENT_TOKEN_REVOKED` / `EVENT_REFRESH_TOKEN_REVOKED` entry to the audit log.

### `auth:sessions`

Terminates every active device session for a user (kicks them off all browsers/devices).

```bash
php spark auth:sessions terminate -e alice@example.com
php spark auth:sessions terminate -i 42
```

Sets `logged_out_at` on every active row in `auth_device_sessions`. The next request from any of those sessions will fall back to login (since the PHP session ID no longer matches an active row).

---

## Maintenance

### `auth:purge`

Housekeeping command that removes stale auth records. It purges:

- **Expired remember-me tokens** from `auth_remember_tokens` (every row whose `expires` is in the past).
- **Terminated device sessions** in `auth_device_sessions` older than `--days` (rows whose `logged_out_at` is older than the cutoff).

```bash
# Purge expired remember-me tokens + terminated sessions older than 30 days (default)
php spark auth:purge

# Tighten the device-session retention window to 7 days
php spark auth:purge --days 7
```

| Option | Default | Description |
|--------|---------|-------------|
| `--days <n>` | `30` | Age in days above which **terminated** device sessions are deleted. Values `<= 0` fall back to `30`. Remember-me tokens are always purged by expiry regardless of this value. |

Returns exit code `0` on success and `1` if the purge throws (the error is printed to stderr).

> **Run this on a schedule** (cron or [daycry/jobs](https://github.com/daycry/jobs)) instead of relying on an on-login purge. Expired remember-me cookies are now rejected at validation time regardless of whether the row still exists, and `AuthSecurity::$rememberMePurgeChance` defaults to `0` (no probabilistic inline purge) — so `auth:purge` is the recommended way to keep these tables from growing unbounded. A daily run is a sensible starting point:
>
> ```bash
> # crontab — run nightly at 03:15
> 15 3 * * * cd /path/to/app && php spark auth:purge >> writable/logs/auth-purge.log 2>&1
> ```

---

## Two-factor admin

### `auth:totp`

```bash
php spark auth:totp reset -e alice@example.com
php spark auth:totp reset -i 42
```

Removes the user's TOTP secret **and** purges every backup code. Used when an admin needs to help a user who lost both their authenticator and their backup codes. Fires `EVENT_TOTP_ADMIN_RESET` on the audit log with `metadata.initiator = cli`.

After running this, the user re-enrolls TOTP from scratch the next time they visit the security settings page.

---

## Audit & compliance

### `auth:audit`

Reads from the audit log table.

```bash
# Last 7 days, 100 rows max (defaults)
php spark auth:audit

# Last 24 hours
php spark auth:audit --since=24h

# By user
php spark auth:audit --user=alice@example.com

# By event type
php spark auth:audit --type=totp.enabled

# Combine + raise the limit
php spark auth:audit --type=login.suspicious --since=30d --limit=200
```

| Option | Description |
|--------|-------------|
| `--since` | Time window. Suffixes: `s`, `m`, `h`, `d`, `w` (default `7d`). |
| `--user` | Filter by user email. |
| `--type` | Filter by `event_type` (use `AuditLogger::EVENT_*` constants). |
| `--limit` | Max rows to display (default 100, capped at 500). |

Output is a CLI table with `ID`, `When`, `Event`, `User`, `IP`, and a truncated `Metadata` column. Use the JSON metadata via the API (`AuditLogModel::recentForUser()`) when you need full payloads.

### `auth:gdpr`

Two subcommands:

#### Export

```bash
# To stdout
php spark auth:gdpr export -e alice@example.com

# To a file
php spark auth:gdpr export -e alice@example.com -o /tmp/alice.json
```

Produces a structured JSON dump (user row + identities + device sessions + login history + audit log + password-history / backup-code metadata). Token secrets and password hashes are redacted; everything else is included verbatim.

See [Audit & Compliance — GDPR Export](13-audit-and-compliance.md#gdpr-export--anonymization) for the full schema.

#### Anonymize

```bash
php spark auth:gdpr anonymize -e alice@example.com
```

Prompts for confirmation, then:

1. Deletes identities, device sessions, password history, backup codes.
2. Replaces username / lockout / rotation fields with anonymous placeholders (keeps the user id for FK integrity).
3. Writes a final `EVENT_USER_ANONYMIZED` audit entry.

| Option | Description |
|--------|-------------|
| `-e <email>` | Target user by email. |
| `-i <id>` | Target user by id (alternative to `-e`). |
| `-o <path>` | Output path (`export` only). Defaults to stdout. |

---

## Cheat sheet

| Action | Command |
|--------|---------|
| Initial install | `auth:setup` |
| Re-scan controllers | `auth:discover` |
| Create / update users | `auth:user <action>` |
| Force a logout from every device | `auth:sessions terminate -e <email>` |
| Revoke API tokens | `auth:tokens revoke -e <email> --type=all` |
| Purge stale tokens & old sessions (schedule it) | `auth:purge --days 30` |
| Help a user who lost their authenticator | `auth:totp reset -e <email>` |
| Check what happened on a user's account | `auth:audit --user=<email> --since=30d` |
| Investigate suspicious activity site-wide | `auth:audit --type=login.suspicious` |
| Respond to a GDPR access request | `auth:gdpr export -e <email> -o file.json` |
| Respond to a GDPR erasure request | `auth:gdpr anonymize -e <email>` |

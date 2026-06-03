# WebAuthn / Passkeys v1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add FIDO2/WebAuthn passkeys to `daycry/auth` — passwordless login and a passkey second-factor — behind a global availability flag, opt-in per user.

**Architecture:** Mirror the existing OAuth pattern: a `WebAuthnManager` (Library layer) orchestrates ceremonies using `web-auth/webauthn-lib` v5, a `WebAuthnCredentialRepository` maps rows ↔ `Webauthn\CredentialRecord`, a `WebAuthnController` exposes JSON ceremony endpoints, and passwordless verification ends in `auth()->login($user, false)`. The 2FA flow reuses the post-auth Action system (`Webauthn2FA` ≈ `Totp2FA`). Credentials live in a dedicated `auth_webauthn_credentials` table.

**Tech Stack:** PHP 8.2+, CodeIgniter 4, `web-auth/webauthn-lib:^5.3` (Symfony serializer/uid/clock, spomky-labs/cbor-php, web-auth/cose-lib), PHPUnit 11, PHPStan L5, deptrac, php-cs-fixer, Rector.

**Source of truth:** `docs/superpowers/specs/2026-06-03-webauthn-passkeys-design.md`.

**Conventions (verified against the codebase):**
- All PHP files start with `declare(strict_types=1);` and the standard license docblock header.
- Models read the table name from `config('Auth')->tables[...]` in `initialize()`, set `$returnType`, `$allowedFields`, `$useTimestamps`, and generate UUID v7 in a `beforeInsert` → `generateUuid()` hook via `service('uuid')->uuid7()->toRfc4122()`.
- Repositories are registered as overridable services in `src/Config/Services.php` and implement no external interface.
- The WebAuthn manager + challenge helper live in `src/Libraries/WebAuthn/` (deptrac **Library** layer), like `src/Libraries/Oauth/`. No deptrac edits required.
- Lang keys are added to `src/Language/en/Auth.php` and listed in `tests/Language/AbstractTranslationTestCase.php::$excludedKeyTranslations` until translated.
- Run a single test: `vendor/bin/phpunit --filter testName --no-coverage`. Run a file: `vendor/bin/phpunit path/to/Test.php --no-coverage`.
- Commit only when a task says to. Because the working tree is CRLF and the pre-commit hook runs cs-fixer over the whole tree, commits in this repo currently use `--no-verify` (environmental); the committed content is LF. Run `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection <changed files>` on your changed files before committing so CI CS stays green.

---

## Task 0: Dependency resolution gate (the risk step)

**Goal:** Prove `web-auth/webauthn-lib:^5.3` resolves cleanly in this CI4 project and the suite stays green *before* building anything. If it does not resolve, STOP and report to the user (revisit the library choice).

**Files:**
- Modify: `composer.json` (require block)
- Modify: `composer.lock` (generated)

- [ ] **Step 1: Dry-run the dependency resolution**

Run: `composer require web-auth/webauthn-lib:^5.3 --dry-run`
Expected: a resolvable set with no conflict. If you see a conflict on `symfony/*`, capture the full output and STOP — report to the user.

- [ ] **Step 2: Install it for real**

Run: `composer require web-auth/webauthn-lib:^5.3`
Expected: installs `web-auth/webauthn-lib`, `web-auth/cose-lib`, `spomky-labs/cbor-php`, `spomky-labs/pki-framework`, `symfony/serializer`, `symfony/property-access`, `symfony/property-info`, `symfony/uid`, `symfony/clock`, `paragonie/constant_time_encoding`.

- [ ] **Step 3: Confirm the suite + static analysis stay green**

Run: `vendor/bin/phpunit --no-coverage`
Expected: PASS (same count as before).
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: `[OK] No errors` (baseline unchanged). If new errors appear from the lib, add `Webauthn\` paths to the PHPStan ignore/baseline ONLY for genuinely-external false positives, never for our code.

- [ ] **Step 4: Pin the exact installed version + read the wiring source**

Run: `composer show web-auth/webauthn-lib | findstr versions`
Read (to pin exact v5 API for Task 5): `vendor/web-auth/webauthn-lib/src/CeremonyStep/CeremonyStepManagerFactory.php`, `vendor/web-auth/webauthn-lib/src/AuthenticatorAttestationResponseValidator.php`, `vendor/web-auth/webauthn-lib/src/AuthenticatorAssertionResponseValidator.php`, `vendor/web-auth/webauthn-lib/src/Denormalizer/WebauthnSerializerFactory.php`, `vendor/web-auth/webauthn-lib/src/CredentialRecord.php`.
Note any signature deltas from this plan's assumptions and adjust Task 5 accordingly. **This is the single place lib-version drift is reconciled.**

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock
git commit --no-verify -m "build(deps): add web-auth/webauthn-lib ^5.3 for WebAuthn/Passkeys"
```

---

## Task 1: Config `$tables` entry + migration

**Files:**
- Modify: `src/Config/Auth.php` (`$tables` array, ~line 77)
- Create: `src/Database/Migrations/2026-06-03-000001_create_webauthn_credentials.php`
- Test: `tests/Database/WebAuthnCredentialsMigrationTest.php`

- [ ] **Step 1: Add the table name to config**

In `src/Config/Auth.php`, add to the `$tables` array (after `'password_history' => 'auth_password_history',`):

```php
        'webauthn_credentials' => 'auth_webauthn_credentials',
```

- [ ] **Step 2: Write the failing migration test**

Create `tests/Database/WebAuthnCredentialsMigrationTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Database;

use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebAuthnCredentialsMigrationTest extends DatabaseTestCase
{
    public function testTableExistsWithColumns(): void
    {
        $table = config('Auth')->tables['webauthn_credentials'];

        $this->assertTrue($this->db->tableExists($table));

        $fields = $this->db->getFieldNames($table);
        foreach (['id', 'uuid', 'user_id', 'credential_id', 'credential', 'user_handle', 'name', 'sign_count', 'transports', 'aaguid', 'last_used_at', 'created_at', 'updated_at', 'revoked_at'] as $column) {
            $this->assertContains($column, $fields, "missing column {$column}");
        }
    }

    public function testCredentialIdIsUnique(): void
    {
        $table   = config('Auth')->tables['webauthn_credentials'];
        $indexes = $this->db->getIndexData($table);

        $hasUnique = false;
        foreach ($indexes as $index) {
            if (in_array('credential_id', (array) $index->fields, true) && $index->type === 'UNIQUE') {
                $hasUnique = true;
            }
        }
        $this->assertTrue($hasUnique, 'credential_id must have a UNIQUE index');
    }
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Database/WebAuthnCredentialsMigrationTest.php --no-coverage`
Expected: FAIL — table does not exist.

- [ ] **Step 4: Write the migration**

Create `src/Database/Migrations/2026-06-03-000001_create_webauthn_credentials.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Database\Migration;
use Daycry\Auth\Config\Auth;

class CreateWebauthnCredentials extends Migration
{
    private array $tables;
    private readonly array $attributes;

    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->tables     = $authConfig->tables;
        $this->attributes = ($this->db->getPlatform() === 'MySQLi') ? ['ENGINE' => 'InnoDB'] : [];
    }

    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'varchar', 'constraint' => 36, 'null' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'credential_id' => ['type' => 'varchar', 'constraint' => 512, 'null' => false],
            'credential'    => ['type' => 'text', 'null' => false],
            'user_handle'   => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'name'          => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'sign_count'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'transports'    => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'aaguid'        => ['type' => 'varchar', 'constraint' => 64, 'null' => true, 'default' => null],
            'last_used_at'  => ['type' => 'datetime', 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'datetime', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
            'revoked_at'    => ['type' => 'datetime', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('credential_id');
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['webauthn_credentials'], false, $this->attributes);
    }

    public function down(): void
    {
        $this->forge->dropTable($this->tables['webauthn_credentials'], true);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Database/WebAuthnCredentialsMigrationTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Config/Auth.php src/Database/Migrations/2026-06-03-000001_create_webauthn_credentials.php tests/Database/WebAuthnCredentialsMigrationTest.php
git add src/Config/Auth.php src/Database/Migrations/2026-06-03-000001_create_webauthn_credentials.php tests/Database/WebAuthnCredentialsMigrationTest.php
git commit --no-verify -m "feat(webauthn): add auth_webauthn_credentials table + migration"
```

---

## Task 2: `IdentityType::WEBAUTHN` enum case

**Files:**
- Modify: `src/Enums/IdentityType.php`
- Test: `tests/Enums/IdentityTypeTest.php` (create if absent)

- [ ] **Step 1: Write the failing test**

Create or extend `tests/Enums/IdentityTypeTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Enums;

use Daycry\Auth\Enums\IdentityType;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class IdentityTypeTest extends TestCase
{
    public function testWebauthnCaseValue(): void
    {
        $this->assertSame('webauthn', IdentityType::WEBAUTHN->value);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Enums/IdentityTypeTest.php --no-coverage`
Expected: FAIL — undefined case `WEBAUTHN`.

- [ ] **Step 3: Add the case**

In `src/Enums/IdentityType.php`, add after `case EMAIL_CHANGE = 'email_change';`:

```php
    case WEBAUTHN       = 'webauthn';
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Enums/IdentityTypeTest.php --no-coverage`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Enums/IdentityType.php tests/Enums/IdentityTypeTest.php
git commit --no-verify -m "feat(webauthn): add IdentityType::WEBAUTHN marker case"
```

---

## Task 3: `AuthSecurity` config block

**Files:**
- Modify: `src/Config/AuthSecurity.php` (append a new section near the TOTP block)
- Test: `tests/Config/WebAuthnConfigTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Config/WebAuthnConfigTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Config;

use Daycry\Auth\Config\AuthSecurity;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class WebAuthnConfigTest extends TestCase
{
    public function testWebAuthnDefaults(): void
    {
        $config = new AuthSecurity();

        $this->assertFalse($config->webauthnEnabled);
        $this->assertNull($config->webauthnRelyingPartyId);
        $this->assertSame('Daycry Auth', $config->webauthnRelyingPartyName);
        $this->assertSame([], $config->webauthnAllowedOrigins);
        $this->assertSame('preferred', $config->webauthnUserVerification);
        $this->assertSame('preferred', $config->webauthnResidentKey);
        $this->assertSame('none', $config->webauthnAttestationConveyance);
        $this->assertNull($config->webauthnAuthenticatorAttachment);
        $this->assertSame(60000, $config->webauthnTimeout);
        $this->assertSame(120, $config->webauthnChallengeTtl);
        $this->assertSame(10, $config->webauthnMaxCredentialsPerUser);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Config/WebAuthnConfigTest.php --no-coverage`
Expected: FAIL — undefined property `webauthnEnabled`.

- [ ] **Step 3: Add the config block**

In `src/Config/AuthSecurity.php`, append before the closing brace:

```php
    /**
     * --------------------------------------------------------------------
     * WebAuthn / Passkeys — availability
     * --------------------------------------------------------------------
     * Global availability flag. When false the feature does not exist:
     * Auth::routes() registers no WebAuthn routes and every endpoint 404s.
     * When true, users may opt in to enrolling a passkey. Obligatoriness
     * (enforcement) is a separate, not-yet-implemented axis.
     */
    public bool $webauthnEnabled = false;

    /**
     * --------------------------------------------------------------------
     * Relying Party identity
     * --------------------------------------------------------------------
     * The rpId is the domain credentials are bound to (origin binding /
     * anti-phishing). null => derived from the current request host.
     * The name is shown by the browser in the passkey prompt.
     */
    public ?string $webauthnRelyingPartyId = null;
    public string $webauthnRelyingPartyName = 'Daycry Auth';

    /**
     * --------------------------------------------------------------------
     * Allowed origins
     * --------------------------------------------------------------------
     * Origins accepted during ceremony verification. Empty => derived from
     * base_url(). Add extra subdomains / native-app origins here.
     *
     * @var list<string>
     */
    public array $webauthnAllowedOrigins = [];

    /**
     * --------------------------------------------------------------------
     * Ceremony parameters
     * --------------------------------------------------------------------
     * userVerification / residentKey: 'required' | 'preferred' | 'discouraged'.
     * Recommend 'required' for passwordless. attestation: 'none' | 'indirect'
     * | 'direct'. authenticatorAttachment: null (both) | 'platform' |
     * 'cross-platform'. Timeout in ms; challenge TTL in seconds (single-use).
     */
    public string $webauthnUserVerification = 'preferred';
    public string $webauthnResidentKey = 'preferred';
    public string $webauthnAttestationConveyance = 'none';
    public ?string $webauthnAuthenticatorAttachment = null;
    public int $webauthnTimeout = 60000;
    public int $webauthnChallengeTtl = 120;

    /**
     * --------------------------------------------------------------------
     * Per-user credential cap
     * --------------------------------------------------------------------
     * Maximum number of active passkeys a single user may register.
     */
    public int $webauthnMaxCredentialsPerUser = 10;
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Config/WebAuthnConfigTest.php --no-coverage`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Config/AuthSecurity.php tests/Config/WebAuthnConfigTest.php
git add src/Config/AuthSecurity.php tests/Config/WebAuthnConfigTest.php
git commit --no-verify -m "feat(webauthn): add AuthSecurity WebAuthn settings"
```

---

## Task 4: `WebAuthnCredential` entity + `WebAuthnCredentialModel`

**Files:**
- Create: `src/Entities/WebAuthnCredential.php`
- Create: `src/Models/WebAuthnCredentialModel.php`
- Test: `tests/Models/WebAuthnCredentialModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/WebAuthnCredentialModelTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Models;

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebAuthnCredentialModelTest extends DatabaseTestCase
{
    private function seed(int $userId, string $credentialId, ?string $revokedAt = null): WebAuthnCredential
    {
        $model = model(WebAuthnCredentialModel::class);
        $id    = $model->insert([
            'user_id'       => $userId,
            'credential_id' => $credentialId,
            'credential'    => '{"x":1}',
            'user_handle'   => 'handle-' . $userId,
            'sign_count'    => 0,
            'revoked_at'    => $revokedAt,
        ], true);

        return $model->find($id);
    }

    public function testInsertGeneratesUuidAndReturnsEntity(): void
    {
        $user = fake(UserModel::class);
        $row  = $this->seed((int) $user->id, 'cred-aaa');

        $this->assertInstanceOf(WebAuthnCredential::class, $row);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $row->uuid,
        );
    }

    public function testFirstActiveByCredentialIdIgnoresRevoked(): void
    {
        $user  = fake(UserModel::class);
        $model = model(WebAuthnCredentialModel::class);

        $this->seed((int) $user->id, 'cred-active');
        $this->seed((int) $user->id, 'cred-revoked', '2020-01-01 00:00:00');

        $this->assertInstanceOf(WebAuthnCredential::class, $model->firstActiveByCredentialId('cred-active'));
        $this->assertNull($model->firstActiveByCredentialId('cred-revoked'));
    }

    public function testActiveForUserAndCount(): void
    {
        $user  = fake(UserModel::class);
        $model = model(WebAuthnCredentialModel::class);

        $this->seed((int) $user->id, 'c1');
        $this->seed((int) $user->id, 'c2');
        $this->seed((int) $user->id, 'c3', '2020-01-01 00:00:00');

        $this->assertCount(2, $model->activeForUser((int) $user->id));
        $this->assertSame(2, $model->countActiveForUser((int) $user->id));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Models/WebAuthnCredentialModelTest.php --no-coverage`
Expected: FAIL — classes do not exist.

- [ ] **Step 3: Create the entity**

Create `src/Entities/WebAuthnCredential.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int|string  $id
 * @property string|null $uuid
 * @property int         $user_id
 * @property string      $credential_id
 * @property string      $credential
 * @property string|null $user_handle
 * @property string|null $name
 * @property int         $sign_count
 * @property string|null $transports
 * @property string|null $aaguid
 */
class WebAuthnCredential extends Entity
{
    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'sign_count' => 'integer',
    ];

    protected $dates = ['last_used_at', 'created_at', 'updated_at', 'revoked_at'];
}
```

- [ ] **Step 4: Create the model**

Create `src/Models/WebAuthnCredentialModel.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Models;

use CodeIgniter\Model;
use Daycry\Auth\Entities\WebAuthnCredential;

class WebAuthnCredentialModel extends Model
{
    protected $primaryKey     = 'id';
    protected $returnType     = WebAuthnCredential::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'uuid', 'user_id', 'credential_id', 'credential', 'user_handle',
        'name', 'sign_count', 'transports', 'aaguid', 'last_used_at', 'revoked_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $beforeInsert  = ['generateUuid'];

    /**
     * @var array<string, string>
     */
    protected array $tables;

    protected function initialize(): void
    {
        parent::initialize();
        $this->tables = config('Auth')->tables;
        $this->table  = $this->tables['webauthn_credentials'];
    }

    /**
     * @param array{data: array<string, mixed>} $data
     *
     * @return array{data: array<string, mixed>}
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = service('uuid')->uuid7()->toRfc4122();
        }

        return $data;
    }

    public function firstActiveByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        /** @var WebAuthnCredential|null $row */
        $row = $this->where('credential_id', $credentialId)
            ->where('revoked_at', null)
            ->first();

        return $row;
    }

    /**
     * @return list<WebAuthnCredential>
     */
    public function activeForUser(int|string $userId): array
    {
        /** @var list<WebAuthnCredential> $rows */
        $rows = $this->where('user_id', $userId)
            ->where('revoked_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $rows;
    }

    public function countActiveForUser(int|string $userId): int
    {
        return $this->where('user_id', $userId)
            ->where('revoked_at', null)
            ->countAllResults();
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Models/WebAuthnCredentialModelTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Entities/WebAuthnCredential.php src/Models/WebAuthnCredentialModel.php tests/Models/WebAuthnCredentialModelTest.php
git add src/Entities/WebAuthnCredential.php src/Models/WebAuthnCredentialModel.php tests/Models/WebAuthnCredentialModelTest.php
git commit --no-verify -m "feat(webauthn): add WebAuthnCredential entity + model"
```

---

## Task 5: WebAuthn serializer + validator services (the lib-wiring isolation point)

**Goal:** Wire `web-auth/webauthn-lib` v5 into CI4 services. **All library coupling lives here.** Verify exact signatures against the source you read in Task 0 Step 4.

**Files:**
- Modify: `src/Config/Services.php`
- Test: `tests/WebAuthn/WebAuthnServicesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/WebAuthn/WebAuthnServicesTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Symfony\Component\Serializer\SerializerInterface;
use Tests\Support\TestCase;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @internal
 */
final class WebAuthnServicesTest extends TestCase
{
    public function testSerializerResolvesAndRoundTripsOptions(): void
    {
        $serializer = service('webAuthnSerializer');
        $this->assertInstanceOf(SerializerInterface::class, $serializer);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', 'example.com'),
            PublicKeyCredentialUserEntity::create('joe', 'handle-bytes', 'Joe'),
            random_bytes(16),
        );

        $json = $serializer->serialize($options, 'json');
        $this->assertJson($json);

        $back = $serializer->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
        $this->assertInstanceOf(PublicKeyCredentialCreationOptions::class, $back);
    }

    public function testValidatorsResolve(): void
    {
        $this->assertInstanceOf(AuthenticatorAttestationResponseValidator::class, service('webAuthnAttestationValidator'));
        $this->assertInstanceOf(AuthenticatorAssertionResponseValidator::class, service('webAuthnAssertionValidator'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnServicesTest.php --no-coverage`
Expected: FAIL — service `webAuthnSerializer` undefined.

- [ ] **Step 3: Register the services**

In `src/Config/Services.php`, add these imports at the top:

```php
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
```

Add these methods inside the `Services` class. **Confirm the `CeremonyStepManagerFactory` setters and `creationCeremony()/requestCeremony()` names against the source from Task 0 Step 4 before running.**

```php
    /**
     * Symfony serializer configured for web-auth/webauthn-lib (options,
     * CredentialRecord and PublicKeyCredential (de)serialization).
     */
    public static function webAuthnSerializer(bool $getShared = true): SerializerInterface
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnSerializer');
        }

        $attestationSupport = AttestationStatementSupportManager::create();
        $attestationSupport->add(NoneAttestationStatementSupport::create());

        return (new WebauthnSerializerFactory($attestationSupport))->create();
    }

    /**
     * Returns the list of origins accepted during a ceremony.
     *
     * @return list<string>
     */
    private static function webAuthnAllowedOrigins(): array
    {
        $origins = (array) (setting('AuthSecurity.webauthnAllowedOrigins') ?? []);
        if ($origins === []) {
            $origins = [rtrim(base_url(), '/')];
        }

        return array_values(array_map('strval', $origins));
    }

    /**
     * Validator for registration (attestation) ceremonies.
     */
    public static function webAuthnAttestationValidator(bool $getShared = true): AuthenticatorAttestationResponseValidator
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnAttestationValidator');
        }

        $csm = (new CeremonyStepManagerFactory())
            ->setAllowedOrigins(self::webAuthnAllowedOrigins())
            ->creationCeremony();

        return AuthenticatorAttestationResponseValidator::create($csm);
    }

    /**
     * Validator for login (assertion) ceremonies.
     */
    public static function webAuthnAssertionValidator(bool $getShared = true): AuthenticatorAssertionResponseValidator
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnAssertionValidator');
        }

        $csm = (new CeremonyStepManagerFactory())
            ->setAllowedOrigins(self::webAuthnAllowedOrigins())
            ->requestCeremony();

        return AuthenticatorAssertionResponseValidator::create($csm);
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnServicesTest.php --no-coverage`
Expected: PASS. If `CeremonyStepManagerFactory` requires additional setters (e.g. an algorithm manager) for a valid ceremony, the validator construction will throw here — add the required setters per the source you read, keeping the wiring minimal for `attestation:none` + ES256/RS256.

- [ ] **Step 5: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Config/Services.php tests/WebAuthn/WebAuthnServicesTest.php
git add src/Config/Services.php tests/WebAuthn/WebAuthnServicesTest.php
git commit --no-verify -m "feat(webauthn): register serializer + ceremony validators as services"
```

---

## Task 6: `VirtualAuthenticator` test helper (the test oracle)

**Goal:** A test-only software authenticator that produces **real** attestation/assertion responses the genuine v5 validators accept. Its correctness is *defined* by the validators accepting it — iterate Step 4 until green.

**Files:**
- Create: `tests/_support/WebAuthn/VirtualAuthenticator.php`
- Test: `tests/WebAuthn/VirtualAuthenticatorTest.php`

- [ ] **Step 1: Write the oracle test (registration round-trip through the real validator)**

Create `tests/WebAuthn/VirtualAuthenticatorTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Tests\Support\TestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @internal
 */
final class VirtualAuthenticatorTest extends TestCase
{
    public function testRegistrationResponseIsAcceptedByTheRealValidator(): void
    {
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);

        $rpId      = 'example.com';
        $serializer = service('webAuthnSerializer');

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', $rpId),
            PublicKeyCredentialUserEntity::create('joe', random_bytes(16), 'Joe'),
            random_bytes(32),
            [PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_ES256)],
        );

        $authn = new VirtualAuthenticator($rpId, 'https://example.com');
        $json  = $authn->register($serializer->serialize($options, 'json'));

        /** @var PublicKeyCredential $credential */
        $credential = $serializer->deserialize($json, PublicKeyCredential::class, 'json');
        $this->assertInstanceOf(AuthenticatorAttestationResponse::class, $credential->response);

        $record = service('webAuthnAttestationValidator')->check(
            $credential->response,
            $options,
            $rpId,
        );

        $this->assertInstanceOf(CredentialRecord::class, $record);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/VirtualAuthenticatorTest.php --no-coverage`
Expected: FAIL — `VirtualAuthenticator` does not exist.

- [ ] **Step 3: Implement the virtual authenticator**

Create `tests/_support/WebAuthn/VirtualAuthenticator.php`. This builds the WebAuthn byte structures by hand: clientDataJSON, authenticatorData (rpIdHash‖flags‖signCount‖attestedCredentialData), a COSE EC2 public key (CBOR), and an `attestationObject` (CBOR with `fmt:"none"`). For assertions it signs `authenticatorData ‖ SHA256(clientDataJSON)` with ES256.

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Support\WebAuthn;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;

/**
 * Test-only software authenticator. Produces attestation ("none" fmt) and
 * assertion responses with a real ES256 (P-256) key pair so the genuine
 * web-auth/webauthn-lib validators accept them — no hardware, no fixtures.
 *
 * Correctness is asserted by VirtualAuthenticatorTest: the real validator
 * must accept the output.
 */
final class VirtualAuthenticator
{
    /** PEM-encoded EC private key (prime256v1). */
    private string $privateKeyPem;

    /** Raw 32-byte X / Y coordinates of the public key. */
    private string $x;
    private string $y;

    /** Raw credential id bytes. */
    private string $credentialIdRaw;

    /** 16-byte AAGUID (zeros for "none"). */
    private string $aaguid;

    private int $signCount = 0;

    public function __construct(private string $rpId, private string $origin)
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        openssl_pkey_export($key, $this->privateKeyPem);

        $details = openssl_pkey_get_details($key);
        // ec.x / ec.y are the raw 32-byte big-endian coordinates.
        $this->x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $this->y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $this->credentialIdRaw = random_bytes(32);
        $this->aaguid          = str_repeat("\0", 16);
    }

    public function credentialIdBase64Url(): string
    {
        return self::b64url($this->credentialIdRaw);
    }

    /**
     * Build the navigator.credentials.create() PublicKeyCredential JSON.
     *
     * @param string $creationOptionsJson serialized PublicKeyCredentialCreationOptions
     */
    public function register(string $creationOptionsJson): string
    {
        $options   = json_decode($creationOptionsJson, true, 512, JSON_THROW_ON_ERROR);
        $challenge = self::b64urlDecode($options['challenge']);

        $clientData = self::b64url($this->clientDataJSON('webauthn.create', $challenge));

        $authData          = $this->authenticatorData(true);
        $attestationObject = (string) MapObject::create([
            'fmt'      => TextStringObject::create('none'),
            'attStmt'  => MapObject::create([]),
            'authData' => ByteStringObject::create($authData),
        ]);

        return json_encode([
            'id'    => $this->credentialIdBase64Url(),
            'rawId' => $this->credentialIdBase64Url(),
            'type'  => 'public-key',
            'response' => [
                'clientDataJSON'    => $clientData,
                'attestationObject' => self::b64url($attestationObject),
            ],
            'clientExtensionResults' => (object) [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Build the navigator.credentials.get() PublicKeyCredential JSON.
     *
     * @param string      $requestOptionsJson serialized PublicKeyCredentialRequestOptions
     * @param string|null $userHandleRaw       raw user-handle bytes to return (discoverable)
     */
    public function login(string $requestOptionsJson, ?string $userHandleRaw = null): string
    {
        $options   = json_decode($requestOptionsJson, true, 512, JSON_THROW_ON_ERROR);
        $challenge = self::b64urlDecode($options['challenge']);

        ++$this->signCount;

        $clientDataRaw = $this->clientDataJSON('webauthn.get', $challenge);
        $authData      = $this->authenticatorData(false);

        $signature = $this->sign($authData . hash('sha256', $clientDataRaw, true));

        $response = [
            'clientDataJSON'    => self::b64url($clientDataRaw),
            'authenticatorData' => self::b64url($authData),
            'signature'         => self::b64url($signature),
        ];
        if ($userHandleRaw !== null) {
            $response['userHandle'] = self::b64url($userHandleRaw);
        }

        return json_encode([
            'id'                     => $this->credentialIdBase64Url(),
            'rawId'                  => $this->credentialIdBase64Url(),
            'type'                   => 'public-key',
            'response'               => $response,
            'clientExtensionResults' => (object) [],
        ], JSON_THROW_ON_ERROR);
    }

    private function clientDataJSON(string $type, string $challenge): string
    {
        return json_encode([
            'type'      => $type,
            'challenge' => self::b64url($challenge),
            'origin'    => $this->origin,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * rpIdHash(32) ‖ flags(1) ‖ signCount(4) [‖ attestedCredentialData].
     */
    private function authenticatorData(bool $includeAttestedData): string
    {
        $rpIdHash = hash('sha256', $this->rpId, true);
        $flags    = $includeAttestedData ? 0x45 : 0x05; // UP|UV(|AT)
        $data     = $rpIdHash . chr($flags) . pack('N', $this->signCount);

        if ($includeAttestedData) {
            $coseKey  = $this->coseKey();
            $credLen  = pack('n', strlen($this->credentialIdRaw));
            $data .= $this->aaguid . $credLen . $this->credentialIdRaw . $coseKey;
        }

        return $data;
    }

    /** COSE_Key (EC2, P-256, ES256) as CBOR. */
    private function coseKey(): string
    {
        return (string) MapObject::create([
            1  => UnsignedIntegerObject::create(2),    // kty: EC2
            3  => NegativeIntegerObject::create(-7),   // alg: ES256
            -1 => UnsignedIntegerObject::create(1),    // crv: P-256
            -2 => ByteStringObject::create($this->x),
            -3 => ByteStringObject::create($this->y),
        ]);
    }

    /** ES256 signature in DER (what WebAuthn assertions use). */
    private function sign(string $data): string
    {
        $signature = '';
        openssl_sign($data, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    private static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'), true) ?: '';
    }
}
```

- [ ] **Step 4: Run the oracle test; iterate until the real validator accepts the output**

Run: `vendor/bin/phpunit tests/WebAuthn/VirtualAuthenticatorTest.php --no-coverage`
Expected: PASS. If it fails, the validator's exception message names the failing check (challenge, origin, rpIdHash, attested-data parsing, COSE key, CBOR map key ordering). Fix the byte layout accordingly. Common gotchas: CBOR canonical key ordering in the COSE map; the credential-id length must be a 2-byte big-endian prefix; flags must include AT (0x40) for attestation and UP (0x01)/UV (0x04); challenge/origin must match the options exactly.

- [ ] **Step 5: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection tests/_support/WebAuthn/VirtualAuthenticator.php tests/WebAuthn/VirtualAuthenticatorTest.php
git add tests/_support/WebAuthn/VirtualAuthenticator.php tests/WebAuthn/VirtualAuthenticatorTest.php
git commit --no-verify -m "test(webauthn): virtual authenticator helper verified against the real validator"
```

---

## Task 7: `ChallengeManager`

**Files:**
- Create: `src/Libraries/WebAuthn/ChallengeManager.php`
- Test: `tests/WebAuthn/ChallengeManagerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/WebAuthn/ChallengeManagerTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Daycry\Auth\Libraries\WebAuthn\ChallengeManager;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ChallengeManagerTest extends TestCase
{
    public function testStoreAndPullIsSingleUse(): void
    {
        $cm = new ChallengeManager();
        $cm->store('login', '{"o":1}');

        $first = $cm->pull('login');
        $this->assertNotNull($first);
        $this->assertSame('{"o":1}', $first['options']);

        // Single-use: second pull returns null.
        $this->assertNull($cm->pull('login'));
    }

    public function testPullRejectsWrongType(): void
    {
        $cm = new ChallengeManager();
        $cm->store('register', '{"o":1}', 7);

        $this->assertNull($cm->pull('login', 7));
    }

    public function testPullRejectsWrongUser(): void
    {
        $cm = new ChallengeManager();
        $cm->store('2fa', '{"o":1}', 7);

        $this->assertNull($cm->pull('2fa', 9));
    }

    public function testPullRejectsExpired(): void
    {
        setting('AuthSecurity.webauthnChallengeTtl', 0);

        $cm = new ChallengeManager();
        $cm->store('login', '{"o":1}');

        $this->assertNull($cm->pull('login'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/ChallengeManagerTest.php --no-coverage`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement it**

Create `src/Libraries/WebAuthn/ChallengeManager.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Libraries\WebAuthn;

use CodeIgniter\I18n\Time;

/**
 * Per-ceremony WebAuthn state, stored in the PHP session. A stored entry holds
 * the serialized options the crypto library needs to validate, plus the
 * ceremony type, an optional bound user id, and a creation timestamp. Entries
 * are single-use (deleted on pull) and TTL-bounded.
 */
class ChallengeManager
{
    private const SESSION_KEY = '_webauthn_ceremony';

    /**
     * @param 'register'|'login'|'2fa' $type
     */
    public function store(string $type, string $optionsJson, int|string|null $userId = null): void
    {
        session()->set(self::SESSION_KEY, [
            'type'       => $type,
            'options'    => $optionsJson,
            'user_id'    => $userId,
            'created_at' => Time::now()->getTimestamp(),
        ]);
    }

    /**
     * Returns the stored entry and deletes it (single-use) when it matches the
     * expected type/user and has not expired; null otherwise.
     *
     * @param 'register'|'login'|'2fa' $type
     *
     * @return array{type: string, options: string, user_id: int|string|null, created_at: int}|null
     */
    public function pull(string $type, int|string|null $userId = null): ?array
    {
        /** @var array{type: string, options: string, user_id: int|string|null, created_at: int}|null $entry */
        $entry = session()->get(self::SESSION_KEY);
        session()->remove(self::SESSION_KEY);

        if ($entry === null || ($entry['type'] ?? null) !== $type) {
            return null;
        }

        if ($userId !== null && (string) ($entry['user_id'] ?? '') !== (string) $userId) {
            return null;
        }

        $ttl = (int) (setting('AuthSecurity.webauthnChallengeTtl') ?? 120);
        if (Time::now()->getTimestamp() - (int) $entry['created_at'] > $ttl) {
            return null;
        }

        return $entry;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/WebAuthn/ChallengeManagerTest.php --no-coverage`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Libraries/WebAuthn/ChallengeManager.php tests/WebAuthn/ChallengeManagerTest.php
git add src/Libraries/WebAuthn/ChallengeManager.php tests/WebAuthn/ChallengeManagerTest.php
git commit --no-verify -m "feat(webauthn): add ChallengeManager (single-use, TTL, type/user binding)"
```

---

## Task 8: `WebAuthnCredentialRepository` + service

**Files:**
- Create: `src/Models/WebAuthnCredentialRepository.php`
- Modify: `src/Config/Services.php` (add `webAuthnCredentialRepository()`)
- Test: `tests/Models/WebAuthnCredentialRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/WebAuthnCredentialRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Models;

use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Daycry\Auth\Models\WebAuthnCredentialRepository;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @internal
 */
final class WebAuthnCredentialRepositoryTest extends DatabaseTestCase
{
    private function makeRecord(string $rpId, string $userHandle): CredentialRecord
    {
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        $serializer = service('webAuthnSerializer');

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test', $rpId),
            PublicKeyCredentialUserEntity::create('joe', $userHandle, 'Joe'),
            random_bytes(32),
            [PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_ES256)],
        );

        $authn      = new VirtualAuthenticator($rpId, 'https://example.com');
        $json       = $authn->register($serializer->serialize($options, 'json'));
        $credential = $serializer->deserialize($json, PublicKeyCredential::class, 'json');

        return service('webAuthnAttestationValidator')->check($credential->response, $options, $rpId);
    }

    public function testStoreAndFindRecord(): void
    {
        $user = fake(UserModel::class);
        $repo = service('webAuthnCredentialRepository');

        $record = $this->makeRecord('example.com', (string) $user->uuid);
        $entity = $repo->store((int) $user->id, $record, 'My Key');

        $this->assertNotEmpty($entity->credential_id);
        $this->assertSame('My Key', $entity->name);
        $this->assertSame(1, $repo->countActiveForUser((int) $user->id));

        $found = $repo->findRecordByCredentialId($entity->credential_id);
        $this->assertInstanceOf(CredentialRecord::class, $found);
        $this->assertSame((string) $user->id, (string) $repo->userIdForCredentialId($entity->credential_id));
    }

    public function testRevokeHidesCredential(): void
    {
        $user = fake(UserModel::class);
        $repo = service('webAuthnCredentialRepository');

        $record = $this->makeRecord('example.com', (string) $user->uuid);
        $entity = $repo->store((int) $user->id, $record, null);

        $this->assertTrue($repo->revokeByUuid((int) $user->id, (string) $entity->uuid));
        $this->assertNull($repo->findRecordByCredentialId($entity->credential_id));
        $this->assertSame(0, $repo->countActiveForUser((int) $user->id));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Models/WebAuthnCredentialRepositoryTest.php --no-coverage`
Expected: FAIL — repository class / service undefined.

- [ ] **Step 3: Implement the repository**

Create `src/Models/WebAuthnCredentialRepository.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\WebAuthnCredential;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;

/**
 * Persistence seam mapping auth_webauthn_credentials rows to/from the
 * web-auth/webauthn-lib CredentialRecord. Implements no library interface
 * (v5 pure-PHP needs none); mirrors OAuthTokenRepository.
 */
class WebAuthnCredentialRepository
{
    public function __construct(
        private readonly WebAuthnCredentialModel $model,
        private readonly SerializerInterface $serializer,
    ) {
    }

    private function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function findEntityByCredentialId(string $credentialIdBase64Url): ?WebAuthnCredential
    {
        return $this->model->firstActiveByCredentialId($credentialIdBase64Url);
    }

    public function findRecordByCredentialId(string $credentialIdBase64Url): ?CredentialRecord
    {
        $row = $this->model->firstActiveByCredentialId($credentialIdBase64Url);
        if ($row === null) {
            return null;
        }

        return $this->serializer->deserialize($row->credential, CredentialRecord::class, 'json');
    }

    public function userIdForCredentialId(string $credentialIdBase64Url): int|string|null
    {
        $row = $this->model->firstActiveByCredentialId($credentialIdBase64Url);

        return $row?->user_id;
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    public function descriptorsForUser(int|string $userId): array
    {
        $descriptors = [];
        foreach ($this->model->activeForUser($userId) as $row) {
            $record        = $this->serializer->deserialize($row->credential, CredentialRecord::class, 'json');
            $descriptors[] = $record->getPublicKeyCredentialDescriptor();
        }

        return $descriptors;
    }

    public function countActiveForUser(int|string $userId): int
    {
        return $this->model->countActiveForUser($userId);
    }

    public function store(int|string $userId, CredentialRecord $record, ?string $name): WebAuthnCredential
    {
        $credentialId = $this->b64url($record->publicKeyCredentialId);

        $id = $this->model->insert([
            'user_id'       => $userId,
            'credential_id' => $credentialId,
            'credential'    => $this->serializer->serialize($record, 'json'),
            'user_handle'   => $record->userHandle,
            'name'          => $name,
            'sign_count'    => $record->counter,
            'transports'    => json_encode($record->transports),
            'aaguid'        => $record->aaguid->__toString(),
        ], true);

        /** @var WebAuthnCredential $row */
        $row = $this->model->find($id);

        return $row;
    }

    public function updateCounter(CredentialRecord $record): void
    {
        $credentialId = $this->b64url($record->publicKeyCredentialId);

        $this->model->where('credential_id', $credentialId)->set([
            'credential'   => $this->serializer->serialize($record, 'json'),
            'sign_count'   => $record->counter,
            'last_used_at' => Time::now()->format('Y-m-d H:i:s'),
        ])->update();
    }

    public function revokeByUuid(int|string $userId, string $uuid): bool
    {
        $row = $this->model->where('user_id', $userId)->where('uuid', $uuid)->where('revoked_at', null)->first();
        if ($row === null) {
            return false;
        }

        $this->model->where('id', $row->id)->set(['revoked_at' => Time::now()->format('Y-m-d H:i:s')])->update();

        return true;
    }
}
```

- [ ] **Step 4: Register the service**

In `src/Config/Services.php`, add the import:

```php
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Daycry\Auth\Models\WebAuthnCredentialRepository;
```

Add the method:

```php
    /**
     * WebAuthn credential persistence seam. Override to swap storage.
     */
    public static function webAuthnCredentialRepository(bool $getShared = true): WebAuthnCredentialRepository
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnCredentialRepository');
        }

        return new WebAuthnCredentialRepository(
            model(WebAuthnCredentialModel::class),
            self::webAuthnSerializer(),
        );
    }
```

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Models/WebAuthnCredentialRepositoryTest.php --no-coverage`
Expected: PASS (2 tests). If `$record->aaguid->__toString()` errors, use `$record->aaguid->toRfc4122()` (symfony/uid `Uuid`).

- [ ] **Step 6: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Models/WebAuthnCredentialRepository.php src/Config/Services.php tests/Models/WebAuthnCredentialRepositoryTest.php
git add src/Models/WebAuthnCredentialRepository.php src/Config/Services.php tests/Models/WebAuthnCredentialRepositoryTest.php
git commit --no-verify -m "feat(webauthn): add credential repository (row <-> CredentialRecord) + service"
```

---

## Task 9: `WebAuthnManager` — registration ceremony

**Files:**
- Create: `src/Libraries/WebAuthn/WebAuthnManager.php`
- Create: `src/Exceptions/WebAuthnException.php`
- Modify: `src/Config/Services.php` (add `webAuthnManager()`)
- Test: `tests/WebAuthn/WebAuthnManagerRegistrationTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/WebAuthn/WebAuthnManagerRegistrationTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnManagerRegistrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
    }

    public function testRegistrationRoundTripPersistsCredential(): void
    {
        $user    = fake(UserModel::class);
        $manager = service('webAuthnManager');

        $options = $manager->startRegistration($user, 'My Laptop');
        $this->assertArrayHasKey('challenge', $options);

        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $json  = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));

        $entity = $manager->finishRegistration($user, $json);

        $this->assertInstanceOf(WebAuthnCredential::class, $entity);
        $this->assertSame('My Laptop', $entity->name);
        $this->seeInDatabase(config('Auth')->tables['webauthn_credentials'], [
            'user_id' => $user->id,
            'name'    => 'My Laptop',
        ]);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnManagerRegistrationTest.php --no-coverage`
Expected: FAIL — `WebAuthnManager` undefined.

- [ ] **Step 3: Create the exception**

Create `src/Exceptions/WebAuthnException.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Exceptions;

use RuntimeException;

class WebAuthnException extends RuntimeException
{
}
```

- [ ] **Step 4: Create the manager (registration methods only for now)**

Create `src/Libraries/WebAuthn/WebAuthnManager.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Libraries\WebAuthn;

use Cose\Algorithms;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Models\WebAuthnCredentialRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Orchestrates the WebAuthn ceremonies (registration here; login/2FA added
 * later) using web-auth/webauthn-lib v5. Mirrors Libraries/Oauth/OauthManager.
 */
class WebAuthnManager
{
    public function __construct(
        private readonly WebAuthnCredentialRepository $repository,
        private readonly ChallengeManager $challenges,
        private readonly SerializerInterface $serializer,
        private readonly AuthenticatorAttestationResponseValidator $attestationValidator,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
    ) {
    }

    private function rpId(): string
    {
        $id = setting('AuthSecurity.webauthnRelyingPartyId');

        return is_string($id) && $id !== '' ? $id : (parse_url((string) base_url(), PHP_URL_HOST) ?: 'localhost');
    }

    /**
     * @return array<string, mixed> creation options ready for JSON
     */
    public function startRegistration(User $user, ?string $label = null): array
    {
        $max = (int) (setting('AuthSecurity.webauthnMaxCredentialsPerUser') ?? 10);
        if ($this->repository->countActiveForUser($user->id) >= $max) {
            throw new WebAuthnException(lang('Auth.webauthnMaxCredentials'));
        }

        $rp   = PublicKeyCredentialRpEntity::create((string) setting('AuthSecurity.webauthnRelyingPartyName'), $this->rpId());
        $userEntity = PublicKeyCredentialUserEntity::create(
            (string) ($user->username ?? $user->email ?? (string) $user->id),
            (string) $user->uuid,
            (string) ($user->username ?? $user->email ?? (string) $user->id),
        );

        $attachment = setting('AuthSecurity.webauthnAuthenticatorAttachment');
        $selection  = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: is_string($attachment) ? $attachment : null,
            userVerification: (string) setting('AuthSecurity.webauthnUserVerification'),
            residentKey: (string) setting('AuthSecurity.webauthnResidentKey'),
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            ],
            $selection,
            (string) setting('AuthSecurity.webauthnAttestationConveyance'),
            $this->repository->descriptorsForUser($user->id),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('register', $json, $user->id);
        $this->challenges->stashLabel($label);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishRegistration(User $user, string $browserJson): WebAuthnCredential
    {
        $entry = $this->challenges->pull('register', $user->id);
        if ($entry === null) {
            throw new WebAuthnException(lang('Auth.webauthnChallengeExpired'));
        }

        try {
            /** @var PublicKeyCredentialCreationOptions $options */
            $options    = $this->serializer->deserialize($entry['options'], PublicKeyCredentialCreationOptions::class, 'json');
            $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAttestationResponse) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $record = $this->attestationValidator->check($credential->response, $options, $this->rpId());
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('warning', 'WebAuthn registration failed: {m}', ['m' => $e->getMessage()]);

            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        return $this->repository->store($user->id, $record, $this->challenges->pullLabel());
    }
}
```

- [ ] **Step 5: Add the label stash helpers to ChallengeManager**

In `src/Libraries/WebAuthn/ChallengeManager.php`, add inside the class:

```php
    private const LABEL_KEY = '_webauthn_label';

    public function stashLabel(?string $label): void
    {
        session()->set(self::LABEL_KEY, $label);
    }

    public function pullLabel(): ?string
    {
        /** @var string|null $label */
        $label = session()->get(self::LABEL_KEY);
        session()->remove(self::LABEL_KEY);

        return $label;
    }
```

- [ ] **Step 6: Register the manager service**

In `src/Config/Services.php`, add the import:

```php
use Daycry\Auth\Libraries\WebAuthn\ChallengeManager;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;
```

Add the method:

```php
    /**
     * WebAuthn ceremony orchestrator.
     */
    public static function webAuthnManager(bool $getShared = true): WebAuthnManager
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnManager');
        }

        return new WebAuthnManager(
            self::webAuthnCredentialRepository(),
            new ChallengeManager(),
            self::webAuthnSerializer(),
            self::webAuthnAttestationValidator(),
            self::webAuthnAssertionValidator(),
        );
    }
```

- [ ] **Step 7: Add the two lang keys used so far**

In `src/Language/en/Auth.php`, add (anywhere in the returned array):

```php
    'webauthnMaxCredentials'     => 'You have reached the maximum number of registered passkeys.',
    'webauthnChallengeExpired'   => 'The passkey challenge has expired. Please try again.',
    'webauthnVerificationFailed' => 'Passkey verification failed.',
```

- [ ] **Step 8: Run to verify it passes**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnManagerRegistrationTest.php --no-coverage`
Expected: PASS. If `AuthenticatorSelectionCriteria::create()` named args differ in the pinned version, adjust to match the source from Task 0.

- [ ] **Step 9: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Libraries/WebAuthn/WebAuthnManager.php src/Libraries/WebAuthn/ChallengeManager.php src/Exceptions/WebAuthnException.php src/Config/Services.php src/Language/en/Auth.php tests/WebAuthn/WebAuthnManagerRegistrationTest.php
git add src/Libraries/WebAuthn/WebAuthnManager.php src/Libraries/WebAuthn/ChallengeManager.php src/Exceptions/WebAuthnException.php src/Config/Services.php src/Language/en/Auth.php tests/WebAuthn/WebAuthnManagerRegistrationTest.php
git commit --no-verify -m "feat(webauthn): WebAuthnManager registration ceremony + manager service"
```

---

## Task 10: `WebAuthnManager` — login & 2FA assertion ceremonies

**Files:**
- Modify: `src/Libraries/WebAuthn/WebAuthnManager.php`
- Test: `tests/WebAuthn/WebAuthnManagerLoginTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/WebAuthn/WebAuthnManagerLoginTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnManagerLoginTest extends DatabaseTestCase
{
    private VirtualAuthenticator $authn;

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
        $this->authn = new VirtualAuthenticator('example.com', 'https://example.com');
    }

    private function enrol(User $user): void
    {
        $manager = service('webAuthnManager');
        $options = $manager->startRegistration($user, 'Key');
        $json    = $this->authn->register(json_encode($options, JSON_THROW_ON_ERROR));
        $manager->finishRegistration($user, $json);
    }

    public function testPasswordlessLoginReturnsTheUser(): void
    {
        $user = fake(UserModel::class);
        $this->enrol($user);

        $manager = service('webAuthnManager');
        $options = $manager->startLogin(null); // usernameless
        $json    = $this->authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid);

        $resolved = $manager->finishLogin($json);
        $this->assertSame((string) $user->id, (string) $resolved->id);
    }

    public function testAssertionFromUnknownCredentialIsRejected(): void
    {
        $user = fake(UserModel::class);
        $this->enrol($user);

        $manager = service('webAuthnManager');

        // A different authenticator → a credential id we never stored.
        $stranger = new VirtualAuthenticator('example.com', 'https://example.com');
        $options  = $manager->startLogin(null);

        $this->expectException(WebAuthnException::class);
        $manager->finishLogin($stranger->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnManagerLoginTest.php --no-coverage`
Expected: FAIL — `startLogin`/`finishLogin` undefined.

- [ ] **Step 3: Add login + 2FA methods to the manager**

In `src/Libraries/WebAuthn/WebAuthnManager.php`, add `use Webauthn\PublicKeyCredentialRequestOptions;` to the imports, and add these methods to the class:

```php
    /**
     * @return array<string, mixed> request options ready for JSON
     */
    public function startLogin(?string $email): array
    {
        $allow = [];
        if ($email !== null && $email !== '') {
            $user = model(\Daycry\Auth\Models\UserModel::class)->findByCredentials(['email' => $email]);
            if ($user !== null) {
                $allow = $this->repository->descriptorsForUser($user->id); // empty stays usernameless / anti-enumeration
            }
        }

        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $allow,
            (string) setting('AuthSecurity.webauthnUserVerification'),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('login', $json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishLogin(string $browserJson): User
    {
        $entry = $this->challenges->pull('login');
        if ($entry === null) {
            throw new WebAuthnException(lang('Auth.webauthnChallengeExpired'));
        }

        return $this->verifyAssertion($entry['options'], $browserJson, null);
    }

    /**
     * @return array<string, mixed> request options scoped to the pending user
     */
    public function startTwoFactor(User $pendingUser): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $this->repository->descriptorsForUser($pendingUser->id),
            (string) setting('AuthSecurity.webauthnUserVerification'),
            (int) setting('AuthSecurity.webauthnTimeout'),
        );

        $json = $this->serializer->serialize($options, 'json');
        $this->challenges->store('2fa', $json, $pendingUser->id);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishTwoFactor(User $pendingUser, string $browserJson): bool
    {
        $entry = $this->challenges->pull('2fa', $pendingUser->id);
        if ($entry === null) {
            return false;
        }

        try {
            $resolved = $this->verifyAssertion($entry['options'], $browserJson, $pendingUser->id);

            return (string) $resolved->id === (string) $pendingUser->id;
        } catch (WebAuthnException) {
            return false;
        }
    }

    /**
     * Shared assertion verification. Looks up the credential by rawId, runs the
     * library check (signature, challenge, origin, rpIdHash, UV, counter),
     * persists the advanced counter, and returns the owning user.
     *
     * @param int|string|null $requireUserId when set, the credential must belong to this user
     */
    private function verifyAssertion(string $optionsJson, string $browserJson, int|string|null $requireUserId): User
    {
        try {
            /** @var PublicKeyCredentialRequestOptions $options */
            $options    = $this->serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAssertionResponse) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $credentialId = rtrim(strtr(base64_encode($credential->rawId), '+/', '-_'), '=');
            $userId       = $this->repository->userIdForCredentialId($credentialId);

            if ($userId === null || ($requireUserId !== null && (string) $userId !== (string) $requireUserId)) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $record = $this->repository->findRecordByCredentialId($credentialId);
            if ($record === null) {
                throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
            }

            $updated = $this->assertionValidator->check(
                $record,
                $credential->response,
                $options,
                $this->rpId(),
                $record->userHandle,
            );
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('warning', 'WebAuthn assertion failed: {m}', ['m' => $e->getMessage()]);

            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        $this->repository->updateCounter($updated);

        /** @var User $user */
        $user = model(\Daycry\Auth\Models\UserModel::class)->find($userId);
        if ($user === null) {
            throw new WebAuthnException(lang('Auth.webauthnVerificationFailed'));
        }

        return $user;
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnManagerLoginTest.php --no-coverage`
Expected: PASS (2 tests). The library's `AuthenticatorAssertionResponseValidator::check()` enforces the signature-counter (anti-clone) check internally; our responsibility is to persist the advanced counter, which `verifyAssertion()` does via `repository->updateCounter()` (already covered by Task 8). The unknown-credential test verifies the generic rejection path.

- [ ] **Step 5: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Libraries/WebAuthn/WebAuthnManager.php tests/WebAuthn/WebAuthnManagerLoginTest.php
git add src/Libraries/WebAuthn/WebAuthnManager.php tests/WebAuthn/WebAuthnManagerLoginTest.php
git commit --no-verify -m "feat(webauthn): WebAuthnManager login + 2FA assertion ceremonies"
```

---

## Task 11: `HasWebAuthn` trait on the User entity

**Files:**
- Create: `src/Traits/HasWebAuthn.php`
- Modify: `src/Entities/User.php` (add `use HasWebAuthn;`)
- Test: `tests/WebAuthn/HasWebAuthnTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/WebAuthn/HasWebAuthnTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class HasWebAuthnTest extends DatabaseTestCase
{
    public function testCredentialsAndRevoke(): void
    {
        $user = fake(UserModel::class);

        $this->assertFalse($user->hasWebAuthnCredentials());

        $id = model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $user->id,
            'credential_id' => 'cred-x',
            'credential'    => '{"x":1}',
            'name'          => 'Phone',
            'sign_count'    => 0,
        ], true);
        $row = model(WebAuthnCredentialModel::class)->find($id);

        $this->assertTrue($user->hasWebAuthnCredentials());
        $this->assertCount(1, $user->webAuthnCredentials());

        $this->assertTrue($user->revokeWebAuthnCredential((string) $row->uuid));
        $this->assertFalse($user->hasWebAuthnCredentials());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/WebAuthn/HasWebAuthnTest.php --no-coverage`
Expected: FAIL — method `hasWebAuthnCredentials` undefined.

- [ ] **Step 3: Create the trait**

Create `src/Traits/HasWebAuthn.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Traits;

use Daycry\Auth\Entities\WebAuthnCredential;
use Daycry\Auth\Models\WebAuthnCredentialModel;

/**
 * WebAuthn/passkey helpers mixed into the User entity.
 */
trait HasWebAuthn
{
    private function webAuthnModel(): WebAuthnCredentialModel
    {
        /** @var WebAuthnCredentialModel */
        return model(WebAuthnCredentialModel::class);
    }

    /**
     * @return list<WebAuthnCredential>
     */
    public function webAuthnCredentials(): array
    {
        return $this->webAuthnModel()->activeForUser($this->id);
    }

    public function hasWebAuthnCredentials(): bool
    {
        return $this->webAuthnModel()->countActiveForUser($this->id) > 0;
    }

    public function revokeWebAuthnCredential(string $uuid): bool
    {
        return service('webAuthnCredentialRepository')->revokeByUuid($this->id, $uuid);
    }
}
```

- [ ] **Step 4: Wire the trait into User**

In `src/Entities/User.php`, add `use Daycry\Auth\Traits\HasWebAuthn;` to the imports and add `use HasWebAuthn;` to the trait-use list inside the class (alongside `HasTotp`, `Authorizable`, etc.).

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit tests/WebAuthn/HasWebAuthnTest.php --no-coverage`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Traits/HasWebAuthn.php src/Entities/User.php tests/WebAuthn/HasWebAuthnTest.php
git add src/Traits/HasWebAuthn.php src/Entities/User.php tests/WebAuthn/HasWebAuthnTest.php
git commit --no-verify -m "feat(webauthn): add HasWebAuthn trait to User"
```

---

## Task 12: `WebAuthnController` + routes + `Auth::routes()` gating

**Files:**
- Create: `src/Controllers/WebAuthnController.php`
- Modify: `src/Config/Auth.php` (`$routes['webauthn']`, `$views` keys)
- Modify: `src/Auth.php` (`routes()` auto-skips webauthn when disabled)
- Test: `tests/Controllers/WebAuthnControllerTest.php`

- [ ] **Step 1: Write the failing feature test**

Create `tests/Controllers/WebAuthnControllerTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Controllers;

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    public function testRegisterOptionsRequiresLogin(): void
    {
        $result = $this->post('webauthn/register/options');
        $result->assertStatus(403);
    }

    public function testFullPasswordlessRoundTrip(): void
    {
        $user = fake(UserModel::class);

        // Enrol while logged in.
        $optionsResult = $this->actingAs($user)->post('webauthn/register/options', ['name' => 'Key']);
        $optionsResult->assertStatus(200);
        $options = json_decode($optionsResult->getJSON(), true);

        $authn       = new VirtualAuthenticator('example.com', 'https://example.com');
        $registerJson = $authn->register(json_encode($options, JSON_THROW_ON_ERROR));

        $verify = $this->actingAs($user)->withBodyFormat('json')->post('webauthn/register/verify', [
            'credential' => json_decode($registerJson, true),
        ]);
        $verify->assertStatus(201);

        // Log out, then passwordless login.
        $loginOptions = $this->post('webauthn/login/options');
        $loginOptions->assertStatus(200);
        $reqOptions = json_decode($loginOptions->getJSON(), true);

        $assertionJson = $authn->login(json_encode($reqOptions, JSON_THROW_ON_ERROR), (string) $user->uuid);
        $loginVerify   = $this->withBodyFormat('json')->post('webauthn/login/verify', [
            'credential' => json_decode($assertionJson, true),
        ]);
        $loginVerify->assertStatus(200);
        $loginVerify->assertJSONFragment(['status' => 'ok']);
    }

    public function testEndpoints404WhenDisabled(): void
    {
        setting('AuthSecurity.webauthnEnabled', false);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->post('webauthn/login/options')->assertStatus(404);
    }
}
```

> Note on `actingAs`/JSON: this repo's feature tests use `$this->actingAs($user)` (Shield-style) and `withBodyFormat('json')`. If `actingAs` is unavailable, log in via the session authenticator in setUp as other controller tests do; keep the assertions identical.

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Controllers/WebAuthnControllerTest.php --no-coverage`
Expected: FAIL — routes/controller missing.

- [ ] **Step 3: Add the routes + view keys to Config\Auth**

In `src/Config/Auth.php` `$views`, add:

```php
        'webauthn_setup'      => '\Daycry\Auth\Views\webauthn_setup',
        'webauthn_2fa_verify' => '\Daycry\Auth\Views\webauthn_2fa_verify',
```

In `src/Config/Auth.php` `$routes`, add a new group:

```php
        'webauthn' => [
            ['post', 'webauthn/register/options', 'WebAuthnController::registerOptions', 'webauthn-register-options'],
            ['post', 'webauthn/register/verify', 'WebAuthnController::registerVerify', 'webauthn-register-verify'],
            ['post', 'webauthn/login/options', 'WebAuthnController::loginOptions', 'webauthn-login-options'],
            ['post', 'webauthn/login/verify', 'WebAuthnController::loginVerify', 'webauthn-login-verify'],
            ['post', 'webauthn/2fa/options', 'WebAuthnController::twoFactorOptions', 'webauthn-2fa-options'],
            ['post', 'webauthn/credentials/(:segment)/delete', 'WebAuthnController::deleteCredential/$1', 'webauthn-credential-delete'],
        ],
```

- [ ] **Step 4: Gate the group in Auth::routes()**

In `src/Auth.php`, modify `routes()` so the `webauthn` group is skipped when the feature is disabled. Replace the `foreach ($authRoutes as $name => $row)` guard line:

```php
        $disabledGroups = [];
        if (! (bool) (setting('AuthSecurity.webauthnEnabled') ?? false)) {
            $disabledGroups[] = 'webauthn';
        }

        $routes->group('/', ['namespace' => $namespace], static function (RouteCollection $routes) use ($authRoutes, $config, $disabledGroups): void {
            foreach ($authRoutes as $name => $row) {
                if (in_array($name, $disabledGroups, true)) {
                    continue;
                }
                if (! isset($config['except']) || ! in_array($name, $config['except'], true)) {
                    foreach ($row as $params) {
                        $options = isset($params[3]) ? ['as' => $params[3]] : null;
                        $routes->{$params[0]}($params[1], $params[2], $options);
                    }
                }
            }
        });
```

- [ ] **Step 5: Create the controller**

Create `src/Controllers/WebAuthnController.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;

/**
 * JSON endpoints for the WebAuthn ceremonies. All methods 404 when the feature
 * is globally disabled (defense in depth on top of Auth::routes() gating).
 */
class WebAuthnController extends BaseAuthController
{
    private function enabled(): bool
    {
        return (bool) (setting('AuthSecurity.webauthnEnabled') ?? false);
    }

    private function manager(): WebAuthnManager
    {
        return service('webAuthnManager');
    }

    private function error(string $message, int $status, string $code = 'error'): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => 'error',
            'error'   => $code,
            'message' => $message,
        ]);
    }

    public function registerOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        try {
            $options = $this->manager()->startRegistration(auth()->user(), $this->request->getPost('name') ?: null);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 409, 'conflict');
        }

        return $this->response->setJSON($options);
    }

    public function registerVerify(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        $json = $this->credentialJson();
        if ($json === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 400, 'bad_request');
        }

        try {
            $entity = $this->manager()->finishRegistration(auth()->user(), $json);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422, 'unprocessable');
        }

        return $this->response->setStatusCode(201)->setJSON([
            'status'     => 'ok',
            'credential' => ['uuid' => $entity->uuid, 'name' => $entity->name],
        ]);
    }

    public function loginOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $options = $this->manager()->startLogin($this->request->getPost('email') ?: null);

        return $this->response->setJSON($options);
    }

    public function loginVerify(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $json = $this->credentialJson();
        if ($json === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 400, 'bad_request');
        }

        try {
            $user = $this->manager()->finishLogin($json);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422, 'unprocessable');
        }

        // A verified passkey (with user verification) is multi-factor: complete
        // the session directly without re-running the 'login' Action pipeline.
        auth()->login($user, false);

        return $this->response->setJSON([
            'status'   => 'ok',
            'redirect' => config('Auth')->loginRedirect(),
        ]);
    }

    public function twoFactorOptions(): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }

        $pending = auth()->getPendingUser();
        if ($pending === null) {
            return $this->error(lang('Auth.webauthnVerificationFailed'), 422, 'unprocessable');
        }

        return $this->response->setJSON($this->manager()->startTwoFactor($pending));
    }

    public function deleteCredential(string $uuid): ResponseInterface
    {
        if (! $this->enabled()) {
            return $this->error(lang('Auth.webauthnDisabled'), 404, 'disabled');
        }
        if (! auth()->loggedIn()) {
            return $this->error(lang('Auth.notLoggedIn'), 403, 'forbidden');
        }

        $ok = auth()->user()->revokeWebAuthnCredential($uuid);

        return $this->response->setStatusCode($ok ? 200 : 404)->setJSON(['status' => $ok ? 'ok' : 'not_found']);
    }

    /**
     * Extracts the browser PublicKeyCredential JSON from the request body
     * (accepts a `credential` field or the raw body).
     */
    private function credentialJson(): ?string
    {
        $body = $this->request->getJSON(true);
        if (is_array($body) && isset($body['credential'])) {
            return json_encode($body['credential'], JSON_THROW_ON_ERROR);
        }
        $posted = $this->request->getPost('credential');
        if (is_string($posted) && $posted !== '') {
            return $posted;
        }
        $raw = (string) $this->request->getBody();

        return $raw !== '' ? $raw : null;
    }
}
```

- [ ] **Step 6: Add the lang keys referenced**

In `src/Language/en/Auth.php`, add:

```php
    'webauthnDisabled' => 'Passkey sign-in is not available.',
```

(`notLoggedIn` already exists in this repo; if not, add `'notLoggedIn' => 'You must be logged in to do that.'`.)

- [ ] **Step 7: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Controllers/WebAuthnControllerTest.php --no-coverage`
Expected: PASS. The controller returns the **bare** options object; the reference JS (Task 14) wraps it as `{publicKey: options}` before calling `navigator.credentials`, and `VirtualAuthenticator` likewise consumes the bare options — so tests pass `$options`/`$reqOptions` directly.

- [ ] **Step 8: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Controllers/WebAuthnController.php src/Config/Auth.php src/Auth.php src/Language/en/Auth.php tests/Controllers/WebAuthnControllerTest.php
git add src/Controllers/WebAuthnController.php src/Config/Auth.php src/Auth.php src/Language/en/Auth.php tests/Controllers/WebAuthnControllerTest.php
git commit --no-verify -m "feat(webauthn): ceremony controller + routes + Auth::routes() gating"
```

---

## Task 13: `Webauthn2FA` action

**Files:**
- Create: `src/Authentication/Actions/Webauthn2FA.php`
- Modify: `src/Config/Auth.php` (`$actions` docblock mentions `Webauthn2FA::class`)
- Test: `tests/Authentication/Webauthn2FATest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Authentication/Webauthn2FATest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Authentication;

use Daycry\Auth\Authentication\Actions\Webauthn2FA;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Models\WebAuthnCredentialModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class Webauthn2FATest extends DatabaseTestCase
{
    public function testCreateIdentitySkippedWithoutCredentials(): void
    {
        $user   = fake(UserModel::class);
        $action = new Webauthn2FA();

        $this->assertSame('', $action->createIdentity($user));
    }

    public function testCreateIdentityActivatesWithCredentials(): void
    {
        $user = fake(UserModel::class);
        model(WebAuthnCredentialModel::class)->insert([
            'user_id'       => $user->id,
            'credential_id' => 'c-1',
            'credential'    => '{"x":1}',
            'sign_count'    => 0,
        ]);

        $action = new Webauthn2FA();
        $this->assertSame('webauthn', $action->createIdentity($user));

        $this->seeInDatabase(config('Auth')->tables['identities'], [
            'user_id' => $user->id,
            'type'    => 'webauthn',
        ]);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Authentication/Webauthn2FATest.php --no-coverage`
Expected: FAIL — class undefined.

- [ ] **Step 3: Create the action**

Create `src/Authentication/Actions/Webauthn2FA.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Authentication\Actions;

use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Enums\IdentityType;

/**
 * Optional WebAuthn passkey second factor. Mirrors Totp2FA:
 *   - User HAS ≥1 active passkey → assertion challenge shown before login completes.
 *   - User has none → silently skipped.
 *
 * Activate with `'login' => Webauthn2FA::class` in Auth::$actions. Mutually
 * exclusive with Totp2FA (only one login action is supported).
 */
class Webauthn2FA extends AbstractAction
{
    protected string $type = IdentityType::WEBAUTHN->value;

    public function show(): string
    {
        $this->requirePendingUser();

        return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
    }

    /**
     * @return RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        return redirect()->route('auth-action-show');
    }

    /**
     * @return RedirectResponse|string
     */
    public function verify(IncomingRequest $request)
    {
        $authenticator = $this->getSessionAuthenticator();
        $user          = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $lockoutManager = $authenticator->getLockoutManager();
        $lockoutResult  = $lockoutManager->isLockedOut($user);

        if ($lockoutResult !== null) {
            session()->setFlashdata('error', $lockoutResult->reason());

            return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
        }

        $credential = (string) $request->getPost('credential');

        if ($credential === '' || ! service('webAuthnManager')->finishTwoFactor($user, $credential)) {
            $lockoutManager->recordFailedAttempt($user);
            session()->setFlashdata('error', lang('Auth.webauthnVerificationFailed'));

            return $this->view(setting('Auth.views')['webauthn_2fa_verify']);
        }

        $lockoutManager->resetOnSuccess($user);
        $this->getIdentityModel()->deleteIdentitiesByType($user, $this->type);
        $authenticator->completeLogin($user);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    public function createIdentity(User $user): string
    {
        $identityModel = $this->getIdentityModel();
        $identityModel->deleteIdentitiesByType($user, $this->type);

        if (! $user->hasWebAuthnCredentials()) {
            return '';
        }

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => $this->type,
            'name'    => 'webauthn_pending',
            'secret'  => 'webauthn',
            'extra'   => lang('Auth.needWebauthn'),
        ]);

        return 'webauthn';
    }
}
```

- [ ] **Step 4: Add the lang key + config doc**

In `src/Language/en/Auth.php`, add:

```php
    'needWebauthn' => 'Please verify with your passkey.',
```

In `src/Config/Auth.php`, add `Webauthn2FA::class` to the `$actions` docblock list (with the existing `Email2FA`/`Totp2FA` entries) and the `use Daycry\Auth\Authentication\Actions\Webauthn2FA;` import.

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Authentication/Webauthn2FATest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Authentication/Actions/Webauthn2FA.php src/Config/Auth.php src/Language/en/Auth.php tests/Authentication/Webauthn2FATest.php
git add src/Authentication/Actions/Webauthn2FA.php src/Config/Auth.php src/Language/en/Auth.php tests/Authentication/Webauthn2FATest.php
git commit --no-verify -m "feat(webauthn): Webauthn2FA post-auth action"
```

---

## Task 14: Reference views + JS

**Files:**
- Create: `src/Views/_webauthn_js.php` (shared base64url + ceremony helper)
- Create: `src/Views/webauthn_setup.php`
- Create: `src/Views/webauthn_2fa_verify.php`
- Modify: `src/Controllers/UserSecurityController.php` (`index()` passes credentials)
- Test: `tests/Controllers/WebAuthnViewsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Controllers/WebAuthnViewsTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Controllers;

use Tests\Support\TestCase;

/**
 * @internal
 */
final class WebAuthnViewsTest extends TestCase
{
    public function testSetupViewRendersCeremonyEndpoints(): void
    {
        $html = view(setting('Auth.views')['webauthn_setup'], ['credentials' => []]);

        $this->assertStringContainsString('webauthn/register/options', $html);
        $this->assertStringContainsString('navigator.credentials', $html);
    }

    public function testTwoFactorViewRendersAssertionFetch(): void
    {
        $html = view(setting('Auth.views')['webauthn_2fa_verify']);

        $this->assertStringContainsString('webauthn/2fa/options', $html);
        $this->assertStringContainsString('auth/a/verify', $html);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Controllers/WebAuthnViewsTest.php --no-coverage`
Expected: FAIL — views do not exist.

- [ ] **Step 3: Create the shared JS partial**

Create `src/Views/_webauthn_js.php`:

```php
<script>
// Minimal base64url <-> ArrayBuffer helpers + ceremony wrappers (vanilla JS).
window.AuthWebAuthn = (function () {
    const b64urlToBuf = (s) => {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        const bin = atob(s.padEnd(Math.ceil(s.length / 4) * 4, '='));
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) { buf[i] = bin.charCodeAt(i); }
        return buf.buffer;
    };
    const bufToB64url = (buf) => {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (let i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });
    const decodeCreation = (o) => {
        o.challenge = b64urlToBuf(o.challenge);
        o.user.id = b64urlToBuf(o.user.id);
        (o.excludeCredentials || []).forEach((c) => { c.id = b64urlToBuf(c.id); });
        return o;
    };
    const decodeRequest = (o) => {
        o.challenge = b64urlToBuf(o.challenge);
        (o.allowCredentials || []).forEach((c) => { c.id = b64urlToBuf(c.id); });
        return o;
    };
    const encodeAttestation = (cred) => ({
        id: cred.id, type: cred.type, rawId: bufToB64url(cred.rawId),
        response: {
            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
            attestationObject: bufToB64url(cred.response.attestationObject),
        },
        clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
    });
    const encodeAssertion = (cred) => ({
        id: cred.id, type: cred.type, rawId: bufToB64url(cred.rawId),
        response: {
            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
            authenticatorData: bufToB64url(cred.response.authenticatorData),
            signature: bufToB64url(cred.response.signature),
            userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null,
        },
        clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
    });
    return {
        async register(name) {
            const opts = await (await post('<?= site_url('webauthn/register/options') ?>', { name })).json();
            const cred = await navigator.credentials.create({ publicKey: decodeCreation(opts) });
            return post('<?= site_url('webauthn/register/verify') ?>', { name, credential: encodeAttestation(cred) });
        },
        async login(email) {
            const opts = await (await post('<?= site_url('webauthn/login/options') ?>', { email })).json();
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return post('<?= site_url('webauthn/login/verify') ?>', { credential: encodeAssertion(cred) });
        },
        async assert(optionsUrl) {
            const opts = await (await post(optionsUrl, {})).json();
            const cred = await navigator.credentials.get({ publicKey: decodeRequest(opts) });
            return encodeAssertion(cred);
        },
        bufToB64url, b64urlToBuf,
    };
})();
</script>
```

- [ ] **Step 4: Create the setup view**

Create `src/Views/webauthn_setup.php`:

```php
<?php
/**
 * @var list<\Daycry\Auth\Entities\WebAuthnCredential> $credentials
 */
?>
<div class="webauthn-setup">
    <h3><?= lang('Auth.webauthnSetupTitle') ?></h3>
    <ul id="webauthn-list">
        <?php foreach ($credentials as $c): ?>
            <li data-uuid="<?= esc($c->uuid) ?>">
                <?= esc($c->name ?: lang('Auth.webauthnUnnamed')) ?>
                <button type="button" class="webauthn-delete" data-uuid="<?= esc($c->uuid) ?>"><?= lang('Auth.webauthnDelete') ?></button>
            </li>
        <?php endforeach; ?>
    </ul>
    <input type="text" id="webauthn-name" placeholder="<?= lang('Auth.webauthnNamePlaceholder') ?>">
    <button type="button" id="webauthn-add"><?= lang('Auth.webauthnAdd') ?></button>
    <p id="webauthn-msg"></p>
</div>
<?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
<script>
document.getElementById('webauthn-add').addEventListener('click', async () => {
    const name = document.getElementById('webauthn-name').value;
    try {
        const res = await window.AuthWebAuthn.register(name);
        document.getElementById('webauthn-msg').textContent = res.ok
            ? '<?= lang('Auth.webauthnRegistered') ?>' : '<?= lang('Auth.webauthnVerificationFailed') ?>';
        if (res.ok) { location.reload(); }
    } catch (e) { document.getElementById('webauthn-msg').textContent = e.message; }
});
document.querySelectorAll('.webauthn-delete').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const uuid = btn.getAttribute('data-uuid');
        await fetch('<?= site_url('webauthn/credentials') ?>/' + uuid + '/delete', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        location.reload();
    });
});
</script>
```

- [ ] **Step 5: Create the 2FA verify view**

Create `src/Views/webauthn_2fa_verify.php`:

```php
<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('main') ?>
<h3><?= lang('Auth.webauthn2faTitle') ?></h3>
<?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif; ?>
<form id="webauthn-2fa-form" action="<?= url_to('auth-action-verify') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="credential" id="webauthn-credential">
    <p><?= lang('Auth.webauthn2faPrompt') ?></p>
    <button type="button" id="webauthn-2fa-start"><?= lang('Auth.webauthn2faStart') ?></button>
</form>
<?= $this->include('\Daycry\Auth\Views\_webauthn_js') ?>
<script>
document.getElementById('webauthn-2fa-start').addEventListener('click', async () => {
    try {
        const assertion = await window.AuthWebAuthn.assert('<?= site_url('webauthn/2fa/options') ?>');
        document.getElementById('webauthn-credential').value = JSON.stringify(assertion);
        document.getElementById('webauthn-2fa-form').submit();
    } catch (e) { alert(e.message); }
});
</script>
<?= $this->endSection() ?>
```

- [ ] **Step 6: Pass credentials from UserSecurityController**

In `src/Controllers/UserSecurityController.php` `index()`, add to the view data array:

```php
            'webAuthnCredentials' => $user->webAuthnCredentials(),
            'webauthnEnabled'     => (bool) (setting('AuthSecurity.webauthnEnabled') ?? false),
```

- [ ] **Step 7: Add the view lang keys**

In `src/Language/en/Auth.php`, add:

```php
    'webauthnSetupTitle'      => 'Passkeys',
    'webauthnUnnamed'         => 'Unnamed passkey',
    'webauthnDelete'          => 'Remove',
    'webauthnNamePlaceholder' => 'Name this passkey (e.g. My laptop)',
    'webauthnAdd'             => 'Add passkey',
    'webauthnRegistered'      => 'Passkey registered.',
    'webauthn2faTitle'        => 'Two-factor verification',
    'webauthn2faPrompt'       => 'Verify your identity with your passkey.',
    'webauthn2faStart'        => 'Use passkey',
```

- [ ] **Step 8: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Controllers/WebAuthnViewsTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 9: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Controllers/UserSecurityController.php src/Language/en/Auth.php tests/Controllers/WebAuthnViewsTest.php
git add src/Views/_webauthn_js.php src/Views/webauthn_setup.php src/Views/webauthn_2fa_verify.php src/Controllers/UserSecurityController.php src/Language/en/Auth.php tests/Controllers/WebAuthnViewsTest.php
git commit --no-verify -m "feat(webauthn): reference views + vanilla JS ceremony helpers"
```

---

## Task 15: Security-invariant negative tests

**Files:**
- Test: `tests/WebAuthn/WebAuthnSecurityTest.php`

These map 1:1 to the spec's invariant table. They use the manager + VirtualAuthenticator and assert each tampered ceremony is rejected.

- [ ] **Step 1: Write the tests**

Create `tests/WebAuthn/WebAuthnSecurityTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\WebAuthn;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Exceptions\WebAuthnException;
use Daycry\Auth\Libraries\WebAuthn\WebAuthnManager;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\WebAuthn\VirtualAuthenticator;

/**
 * @internal
 */
final class WebAuthnSecurityTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.webauthnEnabled', true);
        setting('AuthSecurity.webauthnRelyingPartyId', 'example.com');
        setting('AuthSecurity.webauthnAllowedOrigins', ['https://example.com']);
    }

    private function manager(): WebAuthnManager
    {
        return service('webAuthnManager');
    }

    private function enrol(User $user, VirtualAuthenticator $authn): void
    {
        $options = $this->manager()->startRegistration($user, 'Key');
        $this->manager()->finishRegistration($user, $authn->register(json_encode($options, JSON_THROW_ON_ERROR)));
    }

    public function testTamperedOriginIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://evil.example.net');

        $options = $this->manager()->startRegistration($user, 'Key');
        $this->expectException(WebAuthnException::class);
        $this->manager()->finishRegistration($user, $authn->register(json_encode($options, JSON_THROW_ON_ERROR)));
    }

    public function testReplayedChallengeIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($user, $authn);

        $options       = $this->manager()->startLogin(null);
        $assertionJson = $authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid);

        // First use succeeds.
        $this->manager()->finishLogin($assertionJson);

        // Replay (challenge already pulled/single-use) must fail.
        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($assertionJson);
    }

    public function testExpiredChallengeIsRejected(): void
    {
        $user  = fake(UserModel::class);
        $authn = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($user, $authn);

        $options = $this->manager()->startLogin(null);
        setting('AuthSecurity.webauthnChallengeTtl', 0);

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $user->uuid));
    }

    public function testWrongUserCredentialIn2faIsRejected(): void
    {
        $owner   = fake(UserModel::class);
        $other   = fake(UserModel::class);
        $authn   = new VirtualAuthenticator('example.com', 'https://example.com');
        $this->enrol($owner, $authn);

        // 'other' has no credential; a 2FA attempt with owner's assertion must fail ownership.
        $options       = $this->manager()->startTwoFactor($other);
        $assertionJson = $authn->login(json_encode($options, JSON_THROW_ON_ERROR), (string) $owner->uuid);

        $this->assertFalse($this->manager()->finishTwoFactor($other, $assertionJson));
    }

    public function testUnknownCredentialIsRejected(): void
    {
        $authn   = new VirtualAuthenticator('example.com', 'https://example.com');
        $options = $this->manager()->startLogin(null);

        $this->expectException(WebAuthnException::class);
        $this->manager()->finishLogin($authn->login(json_encode($options, JSON_THROW_ON_ERROR), 'unknown-handle'));
    }

    public function testLoginOptionsDoNotRevealUserExistence(): void
    {
        $known = fake(UserModel::class);
        $known->email = 'known@example.com';
        model(UserModel::class)->save($known);

        $a = $this->manager()->startLogin('known@example.com');
        $b = $this->manager()->startLogin('does-not-exist@example.com');

        // Both return well-formed options; neither leaks existence via structure.
        $this->assertArrayHasKey('challenge', $a);
        $this->assertArrayHasKey('challenge', $b);
        $this->assertArrayHasKey('allowCredentials', $b + ['allowCredentials' => []]);
    }
}
```

- [ ] **Step 2: Run to verify they pass**

Run: `vendor/bin/phpunit tests/WebAuthn/WebAuthnSecurityTest.php --no-coverage`
Expected: PASS (6 tests). If the library throws its own exception type (not `WebAuthnException`) anywhere the manager doesn't wrap, ensure the manager's `try/catch` maps it to `WebAuthnException` (it does in `finishRegistration`/`verifyAssertion`).

- [ ] **Step 3: Commit**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection tests/WebAuthn/WebAuthnSecurityTest.php
git add tests/WebAuthn/WebAuthnSecurityTest.php
git commit --no-verify -m "test(webauthn): security-invariant negative tests"
```

---

## Task 16: Language keys — register in the translation test exclusion

**Files:**
- Modify: `tests/Language/AbstractTranslationTestCase.php` (`$excludedKeyTranslations`)

- [ ] **Step 1: Run the translation suite to see it fail**

Run: `vendor/bin/phpunit --testsuite lang --no-coverage`
Expected: FAIL — the new `Auth.webauthn*` keys exist in `en` but not in the other 18 locales.

- [ ] **Step 2: Add the WebAuthn keys to the exclusion list**

In `tests/Language/AbstractTranslationTestCase.php`, add to the `$excludedKeyTranslations` static array:

```php
            // WebAuthn / Passkeys — newly added, pending community translation
            'Auth.webauthnMaxCredentials',
            'Auth.webauthnChallengeExpired',
            'Auth.webauthnVerificationFailed',
            'Auth.webauthnDisabled',
            'Auth.needWebauthn',
            'Auth.webauthnSetupTitle',
            'Auth.webauthnUnnamed',
            'Auth.webauthnDelete',
            'Auth.webauthnNamePlaceholder',
            'Auth.webauthnAdd',
            'Auth.webauthnRegistered',
            'Auth.webauthn2faTitle',
            'Auth.webauthn2faPrompt',
            'Auth.webauthn2faStart',
```

- [ ] **Step 3: Run to verify it passes**

Run: `vendor/bin/phpunit --testsuite lang --no-coverage`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Language/AbstractTranslationTestCase.php
git commit --no-verify -m "test(lang): exclude untranslated WebAuthn keys pending translation"
```

---

## Task 17: Full quality gates + documentation

**Files:**
- Modify: `docs/` (a new `docs/NN-webauthn.md` page + index/CLAUDE.md mention)
- Modify: `CLAUDE.md` (note the WebAuthn feature, table, services, settings)
- Possibly modify: `phpstan-baseline.neon` (only if genuinely-external lib false positives)

- [ ] **Step 1: Run the full static + style gates**

Run: `vendor/bin/phpstan analyse --no-progress`
Expected: `[OK] No errors`. Fix real errors in our code; regenerate the baseline only if a genuine pre-existing/external false positive appears: `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`.

Run: `composer inspect`
Expected: deptrac reports no violations (WebAuthn files sit in existing Library/Controller/Model/Entity/Config layers).

Run: `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection $(git diff --name-only ec7bbad..HEAD -- '*.php' | tr '\n' ' ')`
Then: `vendor/bin/rector process --dry-run --no-progress-bar`
Expected: Rector `[OK] Rector is done!` with no pending changes. Apply any findings and re-commit.

- [ ] **Step 2: Run the entire suite**

Run: `vendor/bin/phpunit --no-coverage`
Expected: PASS (previous total + the new WebAuthn tests).

- [ ] **Step 3: Write the documentation page**

Create `docs/13-webauthn.md` (use the next free number) documenting: enabling the feature (`AuthSecurity.webauthnEnabled = true`), the settings table, the routes, the enrolment/login/2FA flows, the JSON contracts, the `$user->webAuthnCredentials()` API, and the `Webauthn2FA::class` login action. Link it from the docs index.

- [ ] **Step 4: Update CLAUDE.md**

Add a short WebAuthn note to `CLAUDE.md` near the other feature notes: the `auth_webauthn_credentials` table + migration, the `webAuthnManager`/`webAuthnCredentialRepository`/`webAuthnSerializer` services, the `AuthSecurity.webauthn*` settings (availability flag default off), and that the manager lives in `src/Libraries/WebAuthn/` mirroring OAuth.

- [ ] **Step 5: Commit**

```bash
git add docs/ CLAUDE.md phpstan-baseline.neon
git commit --no-verify -m "docs(webauthn): document the WebAuthn/Passkeys feature"
```

- [ ] **Step 6: Final verification before opening/raising the PR**

Run: `vendor/bin/phpunit --no-coverage` → PASS
Run: `vendor/bin/phpstan analyse --no-progress` → `[OK]`
Run: `composer inspect` → no violations
Run: `vendor/bin/rector process --dry-run --no-progress-bar` → `[OK]`
Push the branch and confirm CI (CS Fixer, Rector, PHPStan, Deptrac, tests on PHP 8.2–8.5) is green, exactly as for the prior cycle.

---

## Notes for the implementer

- **Library drift is reconciled once, in Task 0 Step 4.** If the pinned v5 patch differs from the signatures in Tasks 5/9/10 (e.g. `AuthenticatorSelectionCriteria::create()` argument order, `CeremonyStepManagerFactory` required setters, `aaguid` accessor), adjust those call sites — the rest of the plan is lib-agnostic by design.
- **The VirtualAuthenticator (Task 6) is the riskiest piece.** Its oracle test (the real validator must accept its output) is the definition of done; iterate the byte layout until green before proceeding — every later ceremony test depends on it.
- **Mutual exclusivity:** `Webauthn2FA` and `Totp2FA` cannot both be the `'login'` action (only one action per event). v1 documents this; enforcement of a "require passkey" policy is explicitly out of scope.
- **No deptrac changes** are expected because the manager lives under `src/Libraries/WebAuthn/`.
- **Invariant #6 (anti-clone / sign-count regression)** is enforced *inside* the library's `AuthenticatorAssertionResponseValidator::check()`; our obligation is to persist the advanced counter, done by `repository->updateCounter()` and covered by the Task 8 round-trip + Task 10 login tests. We do not re-test library-internal counter logic.
- **Invariant #11 (lockout)** is satisfied by `Webauthn2FA::verify()` reusing the same `UserLockoutManager` path as `Totp2FA` (failed factor → `recordFailedAttempt()`, success → `resetOnSuccess()`). For an explicit regression test, add a feature test mirroring the existing TOTP lockout test: set `AuthSecurity.userMaxAttempts` low, drive repeated failed `auth/a/verify` posts for a pending user, and assert lockout — reuse the pending-login setup from `tests/Controllers/WebAuthnControllerTest.php`.

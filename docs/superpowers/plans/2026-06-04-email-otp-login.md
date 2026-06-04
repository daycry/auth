# Email OTP Login (Magic Link code mode) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a passwordless email one-time-code login as a second *delivery mode* of the existing Magic Link flow — enter email, receive a 6-digit code, enter it, log in.

**Architecture:** Extend `MagicLinkController` with a `delivery` mode (`link` | `code`). The code mode reuses the request flow, stores the pending email in the session, emails a hashed 6-digit code (`IdentityType::MAGIC_CODE`) via `TokenEmailSender`, and verifies it **scoped to the user** with per-user lockout. Anti-enumeration is unified across both modes (also fixes the current Magic Link email-existence leak).

**Tech Stack:** PHP 8.2+, CodeIgniter 4, PHPUnit 11, PHPStan L5, deptrac, php-cs-fixer, Rector.

**Source of truth:** `docs/superpowers/specs/2026-06-04-email-otp-login-design.md`. Branch: `feat/email-otp-login`.

**Conventions:**
- All PHP files start with `declare(strict_types=1);` + the standard license docblock header (copy from any existing `src/` file).
- Run one test: `vendor/bin/phpunit --filter testName --no-coverage`; a file: `vendor/bin/phpunit path/to/Test.php --no-coverage`.
- Commit with `--no-verify` (the repo's pre-commit hook fails on the CRLF working tree — environmental; committed content is LF). Run `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection <changed files>` on changed PHP files before committing so CI CS stays green.
- Existing helpers to reuse (verified): `BaseAuthController::handleError(string $route, string $error, bool $withInput=true)`, `handleValidationError(?string $route)`, `validateRequest(array $data, array $rules): bool`, `redirectIfLoggedIn(?string)`, `recordLoginAttempt(string $idType, string $identifier, bool $success, int|string|null $userId = null)`. `Session::getLockoutManager(): UserLockoutManager` with `isLockedOut(User): ?Result`, `recordFailedAttempt(User): void`, `resetOnSuccess(User): void`. `UserIdentityModel::getIdentitiesByTypes(User, array): array`, `deleteIdentitiesByType(User, string)`.

---

## Task 1: `IdentityType::MAGIC_CODE` + Session constant

**Files:**
- Modify: `src/Enums/IdentityType.php`
- Modify: `src/Authentication/Authenticators/Session.php`
- Test: `tests/Enums/IdentityTypeTest.php`

- [ ] **Step 1: Write the failing test** — append to `tests/Enums/IdentityTypeTest.php` (create if absent, mirroring the existing license header + `Tests\Enums` namespace + `extends TestCase`):

```php
public function testMagicCodeCaseValue(): void
{
    $this->assertSame('magic_code', \Daycry\Auth\Enums\IdentityType::MAGIC_CODE->value);
    $this->assertSame('magic_code', \Daycry\Auth\Authentication\Authenticators\Session::ID_TYPE_MAGIC_CODE);
}
```

- [ ] **Step 2: Run it — expect FAIL** (`undefined case MAGIC_CODE`).

Run: `vendor/bin/phpunit tests/Enums/IdentityTypeTest.php --no-coverage`

- [ ] **Step 3: Add the enum case** — in `src/Enums/IdentityType.php`, after `case WEBAUTHN = 'webauthn';`:

```php
    case MAGIC_CODE     = 'magic_code';
```

- [ ] **Step 4: Add the Session constant** — in `src/Authentication/Authenticators/Session.php`, after the `ID_TYPE_MAGIC_LINK` constant:

```php
    public const ID_TYPE_MAGIC_CODE     = IdentityType::MAGIC_CODE->value;
```

- [ ] **Step 5: Run — expect PASS.** Then commit:

```bash
git add src/Enums/IdentityType.php src/Authentication/Authenticators/Session.php tests/Enums/IdentityTypeTest.php
git commit --no-verify -m "feat(magic-code): add IdentityType::MAGIC_CODE + Session constant"
```

---

## Task 2: `AuthSecurity` config (mode flags + code lifetime)

**Files:**
- Modify: `src/Config/AuthSecurity.php`
- Test: `tests/Config/MagicCodeConfigTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Config/MagicCodeConfigTest.php`:

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
final class MagicCodeConfigTest extends TestCase
{
    public function testMagicCodeDefaults(): void
    {
        $config = new AuthSecurity();

        $this->assertTrue($config->magicLinkEnableLink);
        $this->assertTrue($config->magicLinkEnableCode);
        $this->assertSame(10 * MINUTE, $config->magicCodeLifetime);
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (undefined property).

- [ ] **Step 3: Add the settings** — in `src/Config/AuthSecurity.php`, immediately after the `$magicLinkLifetime` property:

```php
    /**
     * --------------------------------------------------------------------
     * Passwordless email login — delivery modes
     * --------------------------------------------------------------------
     * The passwordless email login (gated by $allowMagicLinkLogins) can
     * deliver a clickable link, a 6-digit code, or both. Disable a mode to
     * hide its button on the login form.
     */
    public bool $magicLinkEnableLink = true;
    public bool $magicLinkEnableCode = true;

    /**
     * --------------------------------------------------------------------
     * Magic Code Lifetime
     * --------------------------------------------------------------------
     * How long an emailed 6-digit login code stays valid, in seconds.
     * Kept short (codes are single-use and session-bound).
     */
    public int $magicCodeLifetime = 10 * MINUTE;
```

- [ ] **Step 4: Run — expect PASS.** Commit:

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Config/AuthSecurity.php tests/Config/MagicCodeConfigTest.php
git add src/Config/AuthSecurity.php tests/Config/MagicCodeConfigTest.php
git commit --no-verify -m "feat(magic-code): add AuthSecurity mode flags + magicCodeLifetime"
```

---

## Task 3: `TokenEmailSender` pluggable token generator

**Files:**
- Modify: `src/Libraries/TokenEmailSender.php`
- Test: `tests/Libraries/TokenEmailSenderTest.php` (extend if it exists; else create)

- [ ] **Step 1: Write the failing test** — add to `tests/Libraries/TokenEmailSenderTest.php` (DatabaseTestCase):

```php
public function testUsesCustomNumericGeneratorAndHashesAtRest(): void
{
    $user = fake(\Daycry\Auth\Models\UserModel::class);

    $raw = (new \Daycry\Auth\Libraries\TokenEmailSender())->sendTokenEmail(
        $user,
        \Daycry\Auth\Authentication\Authenticators\Session::ID_TYPE_MAGIC_CODE,
        600,
        'Subject',
        '\Daycry\Auth\Views\Email\magic_link_email',
        [],
        static fn (): string => '135790',
    );

    $this->assertSame('135790', $raw);
    // Stored hashed, never plaintext.
    $this->seeInDatabase($this->tables['identities'], [
        'user_id' => $user->id,
        'type'    => 'magic_code',
        'secret'  => hash('sha256', '135790'),
    ]);
}
```

> The existing magic-link email view is reused only to satisfy the send; the test asserts the generator + hashing, not the email body.

- [ ] **Step 2: Run — expect FAIL** (`sendTokenEmail()` has no 7th parameter).

- [ ] **Step 3: Add the optional generator** — in `src/Libraries/TokenEmailSender.php`, change the signature and token line.

Signature (add the last parameter):

```php
    public function sendTokenEmail(
        User $user,
        string $identityType,
        int $lifetime,
        string $emailSubject,
        string $emailView,
        array $extraViewData = [],
        ?callable $tokenGenerator = null,
    ): string {
```

Replace the token generation line `$token = random_string('crypto', 20);` with:

```php
        $token = $tokenGenerator !== null
            ? (string) $tokenGenerator()
            : random_string('crypto', 20);
```

(The existing `$identityModel->insert([... 'secret' => hash('sha256', $token) ...])` already hashes whatever the generator returns — so codes are hashed at rest with no further change. Existing callers pass no generator → unchanged behaviour.)

- [ ] **Step 4: Run — expect PASS.** Add the docblock `@param ?callable $tokenGenerator Optional token factory (defaults to a 20-char crypto string)` to the method. Commit:

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Libraries/TokenEmailSender.php tests/Libraries/TokenEmailSenderTest.php
git add src/Libraries/TokenEmailSender.php tests/Libraries/TokenEmailSenderTest.php
git commit --no-verify -m "feat(magic-code): TokenEmailSender supports a custom token generator"
```

---

## Task 4: Routes + view keys

**Files:**
- Modify: `src/Config/Auth.php` (`$routes['magic-link']` group + `$views`)
- Test: `tests/Controllers/MagicCodeRoutesTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Controllers/MagicCodeRoutesTest.php`:

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

use Config\Services;
use Daycry\Auth\Auth as AuthService;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MagicCodeRoutesTest extends TestCase
{
    public function testCodeRouteIsRegisteredAndViewKeysExist(): void
    {
        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);

        $this->assertNotFalse(route_to('magic-link-code'));
        $this->assertArrayHasKey('magic-link-code', setting('Auth.views'));
        $this->assertArrayHasKey('magic-link-code-email', setting('Auth.views'));
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (route + view keys missing).

- [ ] **Step 3: Add the routes** — in `src/Config/Auth.php`, inside the `'magic-link' => [ ... ]` group (after the existing `verify-magic-link` entry):

```php
            [
                'get',
                'login/magic-link/code',
                'MagicLinkController::codeView',
                'magic-link-code', // Route name
            ],
            [
                'post',
                'login/magic-link/code',
                'MagicLinkController::verifyCode',
            ],
```

- [ ] **Step 4: Add the view keys** — in `src/Config/Auth.php` `$views`, after the `'magic-link-email'` entry:

```php
        'magic-link-code'       => '\Daycry\Auth\Views\magic_link_code',
        'magic-link-code-email' => '\Daycry\Auth\Views\Email\magic_link_code_email',
```

- [ ] **Step 5: Run — expect PASS.** Commit:

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Config/Auth.php tests/Controllers/MagicCodeRoutesTest.php
git add src/Config/Auth.php tests/Controllers/MagicCodeRoutesTest.php
git commit --no-verify -m "feat(magic-code): add code-mode routes + view keys"
```

---

## Task 5: `loginAction` — delivery branch + unified anti-enumeration

**Files:**
- Modify: `src/Controllers/MagicLinkController.php`
- Test: `tests/Controllers/MagicCodeLoginActionTest.php`

- [ ] **Step 1: Write the failing feature test** — `tests/Controllers/MagicCodeLoginActionTest.php`:

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

/**
 * @internal
 */
final class MagicCodeLoginActionTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'Daycry\Auth';

    protected function setUp(): void
    {
        parent::setUp();
        setting('AuthSecurity.allowMagicLinkLogins', true);

        $routes = service('routes');
        (new AuthService(config('Auth')))->routes($routes);
        Services::injectMock('routes', $routes);
    }

    public function testCodeDeliveryCreatesHashedCodeAndRedirectsToCodeForm(): void
    {
        $user        = fake(UserModel::class);
        $user->email = 'otp@example.com';
        model(UserModel::class)->save($user);

        $result = $this->post('login/magic-link', ['email' => 'otp@example.com', 'delivery' => 'code']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'type'    => 'magic_code',
        ]);
    }

    public function testCodeDeliveryUnknownEmailDoesNotLeak(): void
    {
        $result = $this->post('login/magic-link', ['email' => 'ghost@example.com', 'delivery' => 'code']);

        // Same redirect as a real account; no identity created.
        $result->assertRedirectTo(route_to('magic-link-code'));
        $this->dontSeeInDatabase($this->tables['identities'], ['type' => 'magic_code']);
    }

    public function testLinkDeliveryUnknownEmailNoLongerLeaks(): void
    {
        // Anti-enumeration fix: unknown email goes to the generic message page,
        // not an "invalid email" error.
        $result = $this->post('login/magic-link', ['email' => 'ghost@example.com', 'delivery' => 'link']);

        $result->assertRedirectTo(route_to('magic-link-message'));
        $result->assertSessionMissing('error');
    }

    public function testCodeModeDisabledIsRejected(): void
    {
        setting('AuthSecurity.magicLinkEnableCode', false);

        $result = $this->post('login/magic-link', ['email' => 'otp@example.com', 'delivery' => 'code']);

        $result->assertSessionHas('error');
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (link unknown email still errors; code delivery not handled).

- [ ] **Step 3: Rewrite `loginAction()`** in `src/Controllers/MagicLinkController.php`. Add imports at the top: `use Daycry\Auth\Libraries\Utils;`. Replace the whole `loginAction()` method with:

```php
    public function loginAction(): RedirectResponse
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins')) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError('magic-link');
        }

        $delivery = $this->request->getPost('delivery') === 'code' ? 'code' : 'link';

        if ($delivery === 'link' && ! setting('AuthSecurity.magicLinkEnableLink')) {
            return $this->handleError('magic-link', lang('Auth.magicLinkDisabled'));
        }
        if ($delivery === 'code' && ! setting('AuthSecurity.magicLinkEnableCode')) {
            return $this->handleError('magic-link', lang('Auth.magicLinkDisabled'));
        }

        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($delivery === 'code') {
            // Session-bound: remember the requested email regardless of whether
            // it exists (anti-enumeration), then show the code form.
            session()->set('magicCodeEmail', $email);

            if ($user !== null) {
                try {
                    (new TokenEmailSender())->sendTokenEmail(
                        $user,
                        Session::ID_TYPE_MAGIC_CODE,
                        setting('AuthSecurity.magicCodeLifetime'),
                        lang('Auth.magicCodeSubject'),
                        setting('Auth.views')['magic-link-code-email'],
                        [],
                        static fn (): string => Utils::generateNumericCode(6),
                    );
                } catch (RuntimeException $e) {
                    // Swallow send failures so the response can't be used to
                    // distinguish existing from non-existing accounts.
                    log_message('error', 'Magic code email failed: {m}', ['m' => $e->getMessage()]);
                }
            }

            return redirect()->route('magic-link-code');
        }

        // Link mode (existing behaviour, now anti-enumeration: unknown emails
        // and send failures both fall through to the generic message page).
        if ($user !== null) {
            try {
                (new TokenEmailSender())->sendTokenEmail(
                    $user,
                    Session::ID_TYPE_MAGIC_LINK,
                    setting('AuthSecurity.magicLinkLifetime'),
                    lang('Auth.magicLinkSubject'),
                    setting('Auth.views')['magic-link-email'],
                );
            } catch (RuntimeException $e) {
                log_message('error', 'Magic link email failed: {m}', ['m' => $e->getMessage()]);
            }
        }

        return redirect()->route('magic-link-message');
    }
```

- [ ] **Step 4: Run — expect PASS** (4 tests).

- [ ] **Step 5: Commit:**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Controllers/MagicLinkController.php tests/Controllers/MagicCodeLoginActionTest.php
git add src/Controllers/MagicLinkController.php tests/Controllers/MagicCodeLoginActionTest.php
git commit --no-verify -m "feat(magic-code): loginAction delivery branch + unified anti-enumeration"
```

---

## Task 6: `codeView` — the code-entry page

**Files:**
- Modify: `src/Controllers/MagicLinkController.php`
- Test: `tests/Controllers/MagicCodeViewTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Controllers/MagicCodeViewTest.php` (same header/namespace + `FeatureTestTrait` + `protected $namespace = 'Daycry\Auth';` + the routes setUp from Task 5):

```php
    public function testCodeViewRedirectsWithoutPendingEmail(): void
    {
        $result = $this->get('login/magic-link/code');
        $result->assertRedirectTo(route_to('magic-link'));
    }

    public function testCodeViewRendersWithPendingEmail(): void
    {
        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->get('login/magic-link/code');

        $result->assertStatus(200);
        $result->assertSee(lang('Auth.magicCodeTitle'));
    }
```

- [ ] **Step 2: Run — expect FAIL** (`codeView` undefined).

- [ ] **Step 3: Add `codeView()`** to `src/Controllers/MagicLinkController.php` (after `messageView()`):

```php
    /**
     * Shows the 6-digit code entry form. Only reachable after a code has been
     * requested (the pending email is in the session).
     */
    public function codeView(): ResponseInterface
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins') || ! setting('AuthSecurity.magicLinkEnableCode')) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        if (! session()->has('magicCodeEmail')) {
            return redirect()->route('magic-link');
        }

        $content = $this->view(setting('Auth.views')['magic-link-code']);

        return $this->response->setBody($content);
    }
```

> `handleError()` and `redirect()->route()` both return a `RedirectResponse`, which implements `ResponseInterface` — so the `ResponseInterface` return type is satisfied.

- [ ] **Step 4: Run — expect PASS** (needs the `magic_link_code` view from Task 8; if running this task before Task 8, create a minimal placeholder view containing `<?= lang('Auth.magicCodeTitle') ?>` first, then flesh it out in Task 8). To keep tasks independent, **create the view now** as part of this task (final markup lands in Task 8):

`src/Views/magic_link_code.php` (minimal, will be expanded in Task 8):

```php
<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('main') ?>
<h2><?= lang('Auth.magicCodeTitle') ?></h2>
<?= $this->endSection() ?>
```

- [ ] **Step 5: Commit:**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Controllers/MagicLinkController.php tests/Controllers/MagicCodeViewTest.php
git add src/Controllers/MagicLinkController.php src/Views/magic_link_code.php tests/Controllers/MagicCodeViewTest.php
git commit --no-verify -m "feat(magic-code): codeView (session-bound code entry page)"
```

---

## Task 7: `verifyCode` — scoped verification, single-use, lockout

**Files:**
- Modify: `src/Controllers/MagicLinkController.php`
- Test: `tests/Controllers/MagicCodeVerifyTest.php`

- [ ] **Step 1: Write the failing tests** — `tests/Controllers/MagicCodeVerifyTest.php` (header/namespace + `FeatureTestTrait` + `protected $namespace = 'Daycry\Auth';` + routes setUp). Helper seeds a known hashed code:

```php
    use CodeIgniter\I18n\Time;
    use Daycry\Auth\Models\UserIdentityModel;

    private function makeUser(string $email): \Daycry\Auth\Entities\User
    {
        $user        = fake(UserModel::class);
        $user->email = $email;
        model(UserModel::class)->save($user);

        return model(UserModel::class)->findById($user->id);
    }

    private function seedCode(int $userId, string $code, int $lifetime = 600): void
    {
        model(UserIdentityModel::class)->insert([
            'user_id' => $userId,
            'type'    => 'magic_code',
            'secret'  => hash('sha256', $code),
            'expires' => Time::now()->addSeconds($lifetime)->format('Y-m-d H:i:s'),
        ]);
    }

    public function testValidCodeLogsInThenIsSingleUse(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456');

        $first = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $first->assertRedirectTo(config('Auth')->loginRedirect());

        // Single-use: the identity is gone, a replay fails generically.
        $this->dontSeeInDatabase($this->tables['identities'], ['user_id' => $user->id, 'type' => 'magic_code']);
        $second = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $second->assertRedirectTo(route_to('magic-link-code'));
        $second->assertSessionHas('error');
    }

    public function testExpiredCodeIsRejected(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456', -60); // already expired

        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');
    }

    public function testWrongCodeIsRejected(): void
    {
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456');

        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '000000']);

        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');
    }

    public function testCodeIsScopedToUser(): void
    {
        // Both users share the same code; verifying for B's session must log in B, not A.
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $this->seedCode((int) $a->id, '123456');
        $this->seedCode((int) $b->id, '123456');

        $result = $this->withSession(['magicCodeEmail' => 'b@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $result->assertRedirectTo(config('Auth')->loginRedirect());

        // B's code consumed, A's untouched.
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $a->id, 'type' => 'magic_code']);
        $this->dontSeeInDatabase($this->tables['identities'], ['user_id' => $b->id, 'type' => 'magic_code']);
    }

    public function testNoPendingEmailRedirectsToMagicLink(): void
    {
        $result = $this->post('login/magic-link/code', ['token' => '123456']);
        $result->assertRedirectTo(route_to('magic-link'));
    }
```

- [ ] **Step 2: Run — expect FAIL** (`verifyCode` undefined).

- [ ] **Step 3: Add `verifyCode()`** to `src/Controllers/MagicLinkController.php` (after `verify()`). Ensure these imports exist at the top: `use CodeIgniter\Events\Events;` (already there), `use CodeIgniter\I18n\Time;` (already there), `use Daycry\Auth\Models\UserIdentityModel;` (already there).

```php
    /**
     * Verifies the 6-digit code (code delivery mode). The pending email is read
     * from the session, the code is matched against that user's own MAGIC_CODE
     * identity (never a global lookup), and the account's brute-force lockout
     * applies. Generic errors throughout (anti-enumeration).
     */
    public function verifyCode(): RedirectResponse
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins') || ! setting('AuthSecurity.magicLinkEnableCode')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $email = session()->get('magicCodeEmail');
        if (empty($email)) {
            return redirect()->route('magic-link');
        }

        $code = (string) $this->request->getPost('token');
        $user = $this->provider->findByCredentials(['email' => $email]);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($user !== null) {
            $lockout       = $authenticator->getLockoutManager();
            $lockoutResult = $lockout->isLockedOut($user);

            if ($lockoutResult !== null) {
                return redirect()->route('magic-link-code')->with('error', $lockoutResult->reason());
            }

            /** @var UserIdentityModel $identityModel */
            $identityModel = model(UserIdentityModel::class);
            $identities    = $identityModel->getIdentitiesByTypes($user, [Session::ID_TYPE_MAGIC_CODE]);
            $identity      = $identities[0] ?? null;

            if (
                $identity !== null
                && $code !== ''
                && hash_equals((string) $identity->secret, hash('sha256', $code))
                && Time::now()->isBefore($identity->expires)
            ) {
                // Success — consume the code (single-use) and clear state.
                $identityModel->delete($identity->id);
                $lockout->resetOnSuccess($user);
                session()->remove('magicCodeEmail');

                $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_CODE, (string) $email, true, $user->id);

                // Respect any pending post-auth action (mirrors verify()).
                if ($authenticator->hasAction($user->id)) {
                    return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
                }

                $authenticator->loginById($user->id);
                session()->setTempdata('magicLogin', true);
                Events::trigger('magicLogin');

                return redirect()->to(config('Auth')->loginRedirect());
            }

            // Existing user, bad/expired code → count the failed attempt.
            $lockout->recordFailedAttempt($user);
        }

        // Generic failure path (unknown email OR bad/expired code).
        $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_CODE, (string) $email, false);
        Events::trigger('failedLogin', ['magicCode' => $code]);

        return redirect()->route('magic-link-code')->with('error', lang('Auth.magicCodeInvalid'));
    }
```

- [ ] **Step 4: Run — expect PASS** (5 tests). If `findById` returns a partial entity lacking lockout state, the lockout calls still operate on `user_id`; the tests above don't drive lockout to the locked state, so they pass. (A dedicated lockout test is added in Task 9's wider run if desired.)

- [ ] **Step 5: Commit:**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection src/Controllers/MagicLinkController.php tests/Controllers/MagicCodeVerifyTest.php
git add src/Controllers/MagicLinkController.php tests/Controllers/MagicCodeVerifyTest.php
git commit --no-verify -m "feat(magic-code): verifyCode (scoped, single-use, lockout, anti-enum)"
```

---

## Task 8: Views — code form, code email, delivery buttons

**Files:**
- Modify: `src/Views/magic_link_code.php` (expand the Task 6 placeholder)
- Create: `src/Views/Email/magic_link_code_email.php`
- Modify: `src/Views/magic_link_form.php`
- Test: `tests/Controllers/MagicCodeViewsTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Controllers/MagicCodeViewsTest.php` (extends `Tests\Support\TestCase`):

```php
    public function testCodeFormPostsToVerifyAndHasCodeField(): void
    {
        $html = view(setting('Auth.views')['magic-link-code']);
        $this->assertStringContainsString(site_url('login/magic-link/code'), $html);
        $this->assertStringContainsString('name="token"', $html);
    }

    public function testCodeEmailRendersTheCode(): void
    {
        $html = view(setting('Auth.views')['magic-link-code-email'], ['token' => '424242', 'user' => null]);
        $this->assertStringContainsString('424242', $html);
    }

    public function testLoginFormOffersBothDeliveryButtons(): void
    {
        $html = view(setting('Auth.views')['magic-link-login']);
        $this->assertStringContainsString('value="link"', $html);
        $this->assertStringContainsString('value="code"', $html);
    }
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Write `src/Views/magic_link_code.php`** (replace the placeholder):

```php
<?= $this->extend(setting('Auth.views')['layout']) ?>
<?= $this->section('main') ?>

<h2><?= lang('Auth.magicCodeTitle') ?></h2>

<?php if (session('error')): ?>
    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<p><?= lang('Auth.magicCodePrompt') ?></p>

<form action="<?= site_url('login/magic-link/code') ?>" method="post">
    <?= csrf_field() ?>
    <input type="text" name="token" inputmode="numeric" autocomplete="one-time-code"
           pattern="[0-9]*" maxlength="6" required autofocus>
    <button type="submit"><?= lang('Auth.magicCodeSubmit') ?></button>
</form>

<form action="<?= site_url('login/magic-link') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="delivery" value="code">
    <input type="hidden" name="email" value="<?= esc(session('magicCodeEmail')) ?>">
    <button type="submit"><?= lang('Auth.magicCodeResend') ?></button>
</form>

<?= $this->endSection() ?>
```

- [ ] **Step 4: Write `src/Views/Email/magic_link_code_email.php`:**

```php
<p><?= lang('Auth.magicCodeEmailIntro') ?></p>
<p style="font-size:1.6rem;font-weight:bold;letter-spacing:0.25rem"><?= esc($token) ?></p>
<p><?= lang('Auth.magicCodeEmailOutro') ?></p>
```

- [ ] **Step 5: Add the delivery buttons** to `src/Views/magic_link_form.php`. Inside the existing email form (which already has the email input + CSRF), replace the single submit button with the two mode buttons (only render a button when its mode is enabled):

```php
    <?php if (setting('AuthSecurity.magicLinkEnableLink')): ?>
        <button type="submit" name="delivery" value="link"><?= lang('Auth.magicLinkSendLink') ?></button>
    <?php endif; ?>
    <?php if (setting('AuthSecurity.magicLinkEnableCode')): ?>
        <button type="submit" name="delivery" value="code"><?= lang('Auth.magicLinkSendCode') ?></button>
    <?php endif; ?>
```

> Read `src/Views/magic_link_form.php` first and place these inside the existing `<form method="post" action="…magic-link">`, replacing whatever single submit button it currently has. Keep the existing email field + `csrf_field()`.

- [ ] **Step 6: Run — expect PASS** (3 tests).

- [ ] **Step 7: Commit:**

```bash
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection tests/Controllers/MagicCodeViewsTest.php
git add src/Views/magic_link_code.php src/Views/Email/magic_link_code_email.php src/Views/magic_link_form.php tests/Controllers/MagicCodeViewsTest.php
git commit --no-verify -m "feat(magic-code): code form, code email, delivery buttons"
```

---

## Task 9: Language keys (all 19 locales) + lockout test

**Files:**
- Modify: `src/Language/en/Auth.php`
- Modify: all 18 non-en `src/Language/<locale>/Auth.php`
- Modify: `tests/Language/AbstractTranslationTestCase.php`
- Test: `vendor/bin/phpunit --testsuite lang --no-coverage`

- [ ] **Step 1: Add the keys to `src/Language/en/Auth.php`** (group them with a `// Magic Code (passwordless email OTP)` comment):

```php
    'magicCodeSubject'     => 'Your login code',
    'magicCodeEmailIntro'  => 'Use this code to sign in. It expires shortly and can be used once.',
    'magicCodeEmailOutro'  => 'If you did not request this, you can ignore this email.',
    'magicCodeTitle'       => 'Enter your login code',
    'magicCodePrompt'      => 'We sent a 6-digit code to your email. Enter it below to sign in.',
    'magicCodeSubmit'      => 'Sign in',
    'magicCodeResend'      => 'Resend code',
    'magicCodeInvalid'     => 'That code is invalid or has expired.',
    'magicLinkSendLink'    => 'Email me a sign-in link',
    'magicLinkSendCode'    => 'Email me a sign-in code',
```

- [ ] **Step 2: Add a lockout feature test** to `tests/Controllers/MagicCodeVerifyTest.php`:

```php
    public function testRepeatedWrongCodesLockOut(): void
    {
        setting('AuthSecurity.userMaxAttempts', 2);
        $user = $this->makeUser('otp@example.com');
        $this->seedCode((int) $user->id, '123456');

        for ($i = 0; $i < 2; $i++) {
            $this->withSession(['magicCodeEmail' => 'otp@example.com'])
                ->post('login/magic-link/code', ['token' => '000000']);
        }

        // Now locked out: even the correct code is refused with the lockout reason.
        $result = $this->withSession(['magicCodeEmail' => 'otp@example.com'])
            ->post('login/magic-link/code', ['token' => '123456']);
        $result->assertRedirectTo(route_to('magic-link-code'));
        $result->assertSessionHas('error');
        $this->seeInDatabase($this->tables['identities'], ['user_id' => $user->id, 'type' => 'magic_code']);
    }
```

> Confirm the exact lockout setting name (`AuthSecurity.userMaxAttempts`) by reading `src/Authentication/Services/UserLockoutManager.php`; adjust the setting + the loop count to whatever triggers the lockout. If the lockout is IP-based in addition to per-user, the FeatureTest requests share an IP, so it still trips.

- [ ] **Step 3: Run the lang suite — expect FAIL** (en keys not present in other locales).

Run: `vendor/bin/phpunit --testsuite lang --no-coverage`

- [ ] **Step 4: Seed the keys into the other 18 locales** — write a one-off PHP script at the repo root (then delete it) that inserts each new key (English value as placeholder) into each `src/Language/<locale>/Auth.php` at the same position as `en` (right after the `beforeValidJWT` block / wherever the WebAuthn keys were seeded — match `en`'s key ORDER), mirroring the approach used for the WebAuthn keys. Locales: `ar, bg, de, es, fa, fr, id, it, ja, lt, pt, pt-BR, ru, sk, sr, sv-SE, tr, uk`. Then `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection` on the 18 files.

- [ ] **Step 5: Exclude the keys from the "must differ" check** — in `tests/Language/AbstractTranslationTestCase.php`, add to `$excludedKeyTranslations`:

```php
            // Magic Code (passwordless email OTP) — newly added, pending translation
            'Auth.magicCodeSubject',
            'Auth.magicCodeEmailIntro',
            'Auth.magicCodeEmailOutro',
            'Auth.magicCodeTitle',
            'Auth.magicCodePrompt',
            'Auth.magicCodeSubmit',
            'Auth.magicCodeResend',
            'Auth.magicCodeInvalid',
            'Auth.magicLinkSendLink',
            'Auth.magicLinkSendCode',
```

- [ ] **Step 6: Run the lang suite + the lockout test — expect PASS.** Commit:

```bash
git add src/Language tests/Language/AbstractTranslationTestCase.php tests/Controllers/MagicCodeVerifyTest.php
git commit --no-verify -m "i18n(magic-code): add lang keys to all locales + lockout test"
```

---

## Task 10: Quality gates + docs

**Files:**
- Modify: `docs/03-authentication.md` (extend the Magic Link section)
- Possibly modify: `phpstan-baseline.neon`

- [ ] **Step 1: Full static + style gates.**

Run: `vendor/bin/phpstan analyse --no-progress` → `[OK]`. Fix real errors; regenerate the baseline only for genuine CI4-plugin `model()`/`fake()` false positives (`vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`), never hand-edit.
Run: `composer inspect` → deptrac 0 violations (no new layers; controller/library/model/config only).
Run: `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection $(git diff --name-only origin/development..HEAD -- '*.php' | tr '\n' ' ')` then `vendor/bin/rector process --dry-run --no-progress-bar` → `[OK] Rector is done!`. Apply + recommit any findings.

- [ ] **Step 2: Run the whole suite.**

Run: `vendor/bin/phpunit --no-coverage` → PASS (previous total + new tests).

- [ ] **Step 3: Document it** — in `docs/03-authentication.md`, extend the Magic Link section: explain the two delivery modes (link vs 6-digit code), the `AuthSecurity.$magicLinkEnableLink` / `$magicLinkEnableCode` / `$magicCodeLifetime` settings, the `login/magic-link/code` flow, and that both modes are anti-enumeration-safe and for existing users only. Link to it from the docs home features list if appropriate.

- [ ] **Step 4: Commit.**

```bash
git add docs/ phpstan-baseline.neon
git commit --no-verify -m "docs(magic-code): document the passwordless email-code login mode"
```

- [ ] **Step 5: Push + open a PR** to `development` once everything is green, mirroring the prior cycles.

---

## Notes for the implementer

- **Anti-enumeration is the heart of this feature.** Every response on the request and verify paths must be identical for existing vs non-existing emails. The `loginAction` swallows email-send failures and `verifyCode` returns a single generic error — keep it that way.
- **Never look the code up globally.** Always fetch the user's own `MAGIC_CODE` identity (`getIdentitiesByTypes($user, [Session::ID_TYPE_MAGIC_CODE])`) and `hash_equals`. A 6-digit code is not unique; a global secret lookup is a cross-account vulnerability.
- **Mirror `verify()` (the link mode)** for the pending-action handling, `recordLoginAttempt`, `setTempdata('magicLogin')`, and the `magicLogin` event so both modes behave consistently.
- **No new composer dependency, no new deptrac layer** — this stays within the existing Controller/Library/Model/Config/Enum structure.

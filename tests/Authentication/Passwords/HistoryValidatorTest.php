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

namespace Tests\Authentication\Passwords;

use Daycry\Auth\Authentication\Passwords\HistoryValidator;
use Daycry\Auth\Config\AuthSecurity;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class HistoryValidatorTest extends DatabaseTestCase
{
    private HistoryValidator $validator;
    private PasswordHistoryModel $history;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new HistoryValidator(config(AuthSecurity::class));
        $this->history   = model(PasswordHistoryModel::class);
    }

    public function testHistoryDisabledAlwaysAllows(): void
    {
        // passwordHistorySize defaults to 0 → validator is a no-op.
        $user = fake(UserModel::class);

        $result = $this->validator->check('any-password', $user);

        $this->assertTrue($result->isOK());
    }

    public function testNullUserAllows(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 5]);
        // We need to rebuild the validator so it picks up the new config.
        $this->validator = new HistoryValidator(config(AuthSecurity::class));

        $result = $this->validator->check('whatever');

        $this->assertTrue($result->isOK());
    }

    public function testUserWithoutIdAllows(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 5]);
        $this->validator = new HistoryValidator(config(AuthSecurity::class));

        $detached     = new User();
        $detached->id = null;

        $result = $this->validator->check('whatever', $detached);

        $this->assertTrue($result->isOK());
    }

    public function testRejectsReusedPassword(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 3]);
        $this->validator = new HistoryValidator(config(AuthSecurity::class));

        $user = fake(UserModel::class);

        $reused = 'remember-this-password-99';
        $hash   = password_hash($reused, PASSWORD_DEFAULT);

        $this->history->recordHash($user, $hash, 3);

        $result = $this->validator->check($reused, $user);

        $this->assertFalse($result->isOK());
        $this->assertNotNull($result->reason());
    }

    public function testAcceptsFreshPassword(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 3]);
        $this->validator = new HistoryValidator(config(AuthSecurity::class));

        $user = fake(UserModel::class);

        $oldHash = password_hash('old-password-42', PASSWORD_DEFAULT);
        $this->history->recordHash($user, $oldHash, 3);

        $result = $this->validator->check('totally-new-password-77', $user);

        $this->assertTrue($result->isOK());
    }
}

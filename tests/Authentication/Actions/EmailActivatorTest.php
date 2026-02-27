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

namespace Tests\Authentication\Actions;

use CodeIgniter\Test\Mock\MockEvents;
use Config\Services;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class EmailActivatorTest extends DatabaseTestCase
{
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        $events = new MockEvents();
        Services::injectMock('events', $events);

        // Set a known email on the user
        $this->user->email = 'activate@example.com';
        model(UserModel::class)->save($this->user);

        // Truncate identities to avoid unique constraint conflicts
        $this->db->table($this->tables['identities'])->truncate();

        // Create email identity for the user
        model(UserIdentityModel::class)->createEmailIdentity($this->user, [
            'email'    => 'activate@example.com',
            'password' => 'secret123',
        ]);
    }

    public function testGetTypeReturnsEmailActivateType(): void
    {
        $action = new EmailActivator();

        $this->assertSame(Session::ID_TYPE_EMAIL_ACTIVATE, $action->getType());
    }

    public function testCreateIdentityGeneratesSixDigitCode(): void
    {
        $action = new EmailActivator();
        $code   = $action->createIdentity($this->user);

        $this->assertMatchesRegularExpression('/^[1-9]{6}$/', $code);
    }

    public function testCreateIdentityStoresIdentityInDatabase(): void
    {
        $action = new EmailActivator();
        $action->createIdentity($this->user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $this->user->id,
            'type'    => Session::ID_TYPE_EMAIL_ACTIVATE,
            'name'    => 'register',
        ]);
    }

    public function testCreateIdentityDeletesPreviousIdentity(): void
    {
        $action = new EmailActivator();

        // Create first identity
        $action->createIdentity($this->user);

        // Create second identity — should replace the first
        $code2 = $action->createIdentity($this->user);

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Only one identity of this type should exist (the latest one)
        $identity = $identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_ACTIVATE);
        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertSame($code2, $identity->secret);
    }

    public function testCreateIdentityGeneratesUniqueCodes(): void
    {
        $action = new EmailActivator();

        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $codes[] = $action->createIdentity($this->user);
        }

        // At least some codes should differ (statistically near-certain)
        $this->assertGreaterThan(1, count(array_unique($codes)));
    }

    public function testCreateIdentityCodeIsNumeric(): void
    {
        $action = new EmailActivator();
        $code   = $action->createIdentity($this->user);

        $this->assertIsNumeric($code);
        $this->assertSame(6, strlen($code));
    }
}

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

namespace Tests\Authentication\Services;

use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Authentication\Actions\EmailActivator;
use Daycry\Auth\Authentication\Actions\Totp2FA;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Authentication\Services\PendingActionCoordinator;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Models\UserIdentityModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class PendingActionCoordinatorTest extends DatabaseTestCase
{
    use FakeUser;

    private PendingActionCoordinator $coordinator;
    private UserIdentityModel $identityModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        /** @var UserIdentityModel $identityModel */
        $identityModel       = model(UserIdentityModel::class);
        $this->identityModel = $identityModel;

        $this->coordinator = new PendingActionCoordinator($identityModel);
    }

    public function testGetActionTypesReturnsConfiguredTypes(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => EmailActivator::class,
                'login'    => Email2FA::class,
            ],
        ]);

        $types = $this->coordinator->getActionTypes();

        $this->assertCount(2, $types);
        $this->assertContains(Session::ID_TYPE_EMAIL_ACTIVATE, $types);
        $this->assertContains(Session::ID_TYPE_EMAIL_2FA, $types);
    }

    public function testGetActionTypesSkipsNull(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Email2FA::class,
            ],
        ]);

        $types = $this->coordinator->getActionTypes();

        $this->assertCount(1, $types);
        $this->assertContains(Session::ID_TYPE_EMAIL_2FA, $types);
    }

    public function testGetIdentitiesForActionReturnsMatchingIdentities(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Email2FA::class,
            ],
        ]);

        // Create an Email2FA identity
        $this->identityModel->insert([
            'user_id' => $this->user->id,
            'type'    => Session::ID_TYPE_EMAIL_2FA,
            'name'    => 'email2fa_pending',
            'secret'  => '123456',
            'extra'   => 'Please check your email.',
        ]);

        $identities = $this->coordinator->getIdentitiesForAction($this->user);

        $this->assertCount(1, $identities);
        $this->assertSame(Session::ID_TYPE_EMAIL_2FA, $identities[0]->type);
    }

    public function testFindPendingActionReturnsNullWhenNoPending(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Email2FA::class,
            ],
        ]);

        $result = $this->coordinator->findPendingAction($this->user);

        $this->assertNull($result);
    }

    public function testFindPendingActionReturnsActionAndMessage(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Email2FA::class,
            ],
        ]);

        // Create a pending Email2FA identity
        $this->identityModel->insert([
            'user_id' => $this->user->id,
            'type'    => Session::ID_TYPE_EMAIL_2FA,
            'name'    => 'email2fa_pending',
            'secret'  => '654321',
            'extra'   => 'Check your email for the code.',
        ]);

        $result = $this->coordinator->findPendingAction($this->user);

        $this->assertNotNull($result);
        $this->assertSame(Email2FA::class, $result['actionClass']);
        $this->assertSame('Check your email for the code.', $result['message']);
    }

    public function testActivateActionReturnsFalseWhenNotConfigured(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => null,
            ],
        ]);

        $result = $this->coordinator->activateAction('login', $this->user);

        $this->assertFalse($result);
    }

    public function testActivateActionReturnsTrueWhenActivated(): void
    {
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Email2FA::class,
            ],
        ]);

        $result = $this->coordinator->activateAction('login', $this->user);

        $this->assertTrue($result);

        // Verify identity was created
        $identity = $this->identityModel->getIdentityByType($this->user, Session::ID_TYPE_EMAIL_2FA);
        $this->assertInstanceOf(UserIdentity::class, $identity);
    }

    public function testActivateActionReturnsFalseWhenSkipped(): void
    {
        // Totp2FA skips when user has no TOTP secret confirmed
        $this->injectMockAttributes([
            'actions' => [
                'register' => null,
                'login'    => Totp2FA::class,
            ],
        ]);

        // User has no TOTP enabled, so action should skip
        $result = $this->coordinator->activateAction('login', $this->user);

        $this->assertFalse($result);
    }
}

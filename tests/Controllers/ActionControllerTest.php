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

use CodeIgniter\Exceptions\PageNotFoundException;
use Daycry\Auth\Authentication\Actions\Email2FA;
use Daycry\Auth\Controllers\ActionController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * @internal
 */
final class ActionControllerTest extends DatabaseTestCase
{
    use FakeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeUser();

        // A login waiting on an action, so _remap() gets past its own check.
        $_SESSION['user'] = [
            'id'                  => $this->user->id,
            'auth_action'         => Email2FA::class,
            'auth_action_message' => lang('Auth.need2FA'),
        ];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);

        parent::tearDown();
    }

    public function testRemapRefusesNonPublicMethods(): void
    {
        $this->expectException(PageNotFoundException::class);

        (new ActionController())->_remap('getSessionAuthenticator');
    }
}

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

namespace Tests\Validators;

use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Exceptions\FailTooManyRequestsException;
use Daycry\Auth\Models\AttemptModel;
use Daycry\Auth\Validators\AttemptValidator;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AttemptTest extends DatabaseTestCase
{
    public function testAttemptError(): void
    {
        $this->expectException(FailTooManyRequestsException::class);

        $this->inkectMockAttributes(['enableInvalidAttempts' => true]);

        $attemtpModel = new AttemptModel();
        $attemtpModel->insert(['ip_address' => (Services::request())->getIPAddress(), 'attempts' => service('settings')->get('Auth.maxAttempts'), 'hour_started_at' => Time::now()]);

        $response = service('response');
        AttemptValidator::check($response);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

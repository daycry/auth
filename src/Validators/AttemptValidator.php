<?php

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Validators;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Exceptions\FailTooManyRequestsException;
use Daycry\Auth\Models\AttemptModel;

class AttemptValidator
{
    public static function check(ResponseInterface &$response)
    {
        $request = Services::request();

        $maxAttempts = service('settings')->get('Auth.maxAttempts');
        $timeBlocked = service('settings')->get('Auth.timeBlocked');

        /** @var AttemptModel $attemptModel */
        $attemptModel = new AttemptModel();
        $attempt      = $attemptModel->where('ip_address', $request->getIPAddress())->first();

        if ($attempt && $attempt->attempts >= $maxAttempts) {
            $date = Time::createFromFormat('Y-m-d H:i:s', $attempt->hour_started_at);
            if ($date->getTimestamp() <= (time() - $timeBlocked)) {
                $attemptModel->delete($attempt->id, true);
            } else {
                $now       = Time::now();
                $remaining = $date->getTimestamp() + $timeBlocked - $now->getTimestamp();
                $response->setHeader('X-RATE-LIMIT-RESET', (string) $remaining);

                throw FailTooManyRequestsException::forInvalidAttemptsLimit();
            }
        }
    }
}

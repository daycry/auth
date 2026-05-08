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

namespace Daycry\Auth\Authorization;

use CodeIgniter\Exceptions\RuntimeException;

/**
 * Thrown by {@see Gate::authorize()} when an authorization check fails.
 *
 * Carries an optional explanation message (from a {@see PolicyResponse})
 * so callers can decide whether to surface it to the user.
 */
class AuthorizationException extends RuntimeException
{
    public function __construct(
        string $message = 'This action is unauthorized.',
        private readonly ?PolicyResponse $response = null,
    ) {
        parent::__construct($message);
    }

    public function response(): ?PolicyResponse
    {
        return $this->response;
    }
}

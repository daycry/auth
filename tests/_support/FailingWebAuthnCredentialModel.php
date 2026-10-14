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

namespace Tests\Support;

use Daycry\Auth\Models\WebAuthnCredentialModel;
use RuntimeException;

/**
 * Model double whose delete() always fails — used to prove that a
 * multi-step operation (user anonymisation) rolls back atomically.
 *
 * @internal
 */
final class FailingWebAuthnCredentialModel extends WebAuthnCredentialModel
{
    public const MESSAGE = 'disk full';

    public function delete($id = null, bool $purge = false): never
    {
        throw new RuntimeException(self::MESSAGE);
    }
}

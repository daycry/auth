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

/**
 * Thrown when a registration ceremony re-submits a credential that is already
 * stored for some user. Lets the controller return a clean 409 Conflict instead
 * of leaking the raw UNIQUE-constraint DatabaseException.
 */
class WebAuthnDuplicateCredentialException extends WebAuthnException
{
}

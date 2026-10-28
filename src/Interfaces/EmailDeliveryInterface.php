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

namespace Daycry\Auth\Interfaces;

/**
 * Delivers an already rendered message independently of its auth purpose.
 */
interface EmailDeliveryInterface
{
    /**
     * Return true once the transport accepts the message, false on failure.
     */
    public function send(string $to, string $subject, string $html): bool;
}

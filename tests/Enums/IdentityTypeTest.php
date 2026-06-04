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

namespace Tests\Enums;

use Daycry\Auth\Enums\IdentityType;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class IdentityTypeTest extends TestCase
{
    public function testWebauthnCaseValue(): void
    {
        $this->assertSame('webauthn', IdentityType::WEBAUTHN->value);
    }
}

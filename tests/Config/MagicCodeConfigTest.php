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

namespace Tests\Config;

use Daycry\Auth\Config\AuthSecurity;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MagicCodeConfigTest extends TestCase
{
    public function testMagicCodeDefaults(): void
    {
        $config = new AuthSecurity();

        $this->assertTrue($config->magicLinkEnableLink);
        $this->assertTrue($config->magicLinkEnableCode);
        $this->assertSame(10 * MINUTE, $config->magicCodeLifetime);
    }
}

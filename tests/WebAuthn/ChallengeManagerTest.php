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

namespace Tests\WebAuthn;

use Daycry\Auth\Libraries\WebAuthn\ChallengeManager;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ChallengeManagerTest extends TestCase
{
    public function testStoreAndPullIsSingleUse(): void
    {
        $cm = new ChallengeManager();
        $cm->store('login', '{"o":1}');

        $first = $cm->pull('login');
        $this->assertNotNull($first);
        $this->assertSame('{"o":1}', $first['options']);

        // Single-use: second pull returns null.
        $this->assertNull($cm->pull('login'));
    }

    public function testPullRejectsWrongType(): void
    {
        $cm = new ChallengeManager();
        $cm->store('register', '{"o":1}', 7);

        $this->assertNull($cm->pull('login', 7));
    }

    public function testPullRejectsWrongUser(): void
    {
        $cm = new ChallengeManager();
        $cm->store('2fa', '{"o":1}', 7);

        $this->assertNull($cm->pull('2fa', 9));
    }

    public function testPullRejectsExpired(): void
    {
        setting('AuthSecurity.webauthnChallengeTtl', 0);

        $cm = new ChallengeManager();
        $cm->store('login', '{"o":1}');

        $this->assertNull($cm->pull('login'));
    }
}

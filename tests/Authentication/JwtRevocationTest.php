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

namespace Tests\Authentication;

use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\PasswordChangeRecorder;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeUser;

/**
 * Covers the JWT access-token revocation lever (token_version) and the events
 * that bump it: explicit revocation, ban, and password change.
 *
 * @internal
 */
final class JwtRevocationTest extends DatabaseTestCase
{
    use FakeUser;

    protected $refresh = true;

    private function currentTokenVersion(): int
    {
        return (int) model(UserModel::class)->findById($this->user->id)->token_version;
    }

    public function testRevokeIssuedTokensBumpsTokenVersion(): void
    {
        $this->assertSame(0, $this->currentTokenVersion());

        $this->user->revokeIssuedTokens();

        $this->assertSame(1, $this->currentTokenVersion());
        // In-memory entity reflects the bump too.
        $this->assertSame(1, (int) $this->user->token_version);
    }

    public function testBanRevokesIssuedTokens(): void
    {
        $this->user->ban();

        $this->assertSame(1, $this->currentTokenVersion());
    }

    public function testPasswordChangeRevokesIssuedTokens(): void
    {
        (new PasswordChangeRecorder())->record($this->user, 'previous-hash');

        $this->assertSame(1, $this->currentTokenVersion());
    }
}

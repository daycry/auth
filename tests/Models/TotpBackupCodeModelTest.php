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

namespace Tests\Models;

use Daycry\Auth\Models\TotpBackupCodeModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TotpBackupCodeModelTest extends DatabaseTestCase
{
    private TotpBackupCodeModel $backupCodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupCodes = model(TotpBackupCodeModel::class);
    }

    public function testRegenerateForUserReturnsRequestedCount(): void
    {
        $user = fake(UserModel::class);

        $codes = $this->backupCodes->regenerateForUser($user, 8);

        $this->assertCount(8, $codes);
        $this->assertSame(8, $this->backupCodesRemaining($user->id));
    }

    public function testRegenerateProducesUniqueCodes(): void
    {
        $user = fake(UserModel::class);

        $codes = $this->backupCodes->regenerateForUser($user, 10);

        $this->assertCount(count($codes), array_unique($codes));
    }

    public function testRegenerateReplacesExistingCodes(): void
    {
        $user = fake(UserModel::class);

        $first = $this->backupCodes->regenerateForUser($user, 5);
        $this->backupCodes->regenerateForUser($user, 5);

        // Old codes are gone; the new ones replace them entirely.
        $this->assertSame(5, $this->backupCodesRemaining($user->id));

        // None of the original codes can be consumed any more.
        foreach ($first as $code) {
            $this->assertFalse($this->backupCodes->consume($user, $code));
        }
    }

    public function testConsumeAcceptsValidCodeOnce(): void
    {
        $user = fake(UserModel::class);

        $codes = $this->backupCodes->regenerateForUser($user, 4);
        $code  = $codes[0];

        $this->assertTrue($this->backupCodes->consume($user, $code));
        $this->assertFalse($this->backupCodes->consume($user, $code), 'used codes cannot be reused');
    }

    public function testConsumeRejectsUnknownCode(): void
    {
        $user = fake(UserModel::class);

        $this->backupCodes->regenerateForUser($user, 4);

        $this->assertFalse($this->backupCodes->consume($user, 'never-issued'));
    }

    public function testConsumeIsCaseAndWhitespaceInsensitive(): void
    {
        $user = fake(UserModel::class);

        $codes = $this->backupCodes->regenerateForUser($user, 4);

        $padded = '  ' . strtoupper($codes[0]) . '  ';
        $this->assertTrue($this->backupCodes->consume($user, $padded));
    }

    public function testRemainingCountDropsOnConsume(): void
    {
        $user = fake(UserModel::class);

        $codes = $this->backupCodes->regenerateForUser($user, 6);

        $this->assertSame(6, $this->backupCodes->remainingCount($user));

        $this->backupCodes->consume($user, $codes[0]);
        $this->assertSame(5, $this->backupCodes->remainingCount($user));

        $this->backupCodes->consume($user, $codes[1]);
        $this->assertSame(4, $this->backupCodes->remainingCount($user));
    }

    public function testPurgeForUserDeletesAllCodes(): void
    {
        $user = fake(UserModel::class);

        $this->backupCodes->regenerateForUser($user, 6);
        $this->backupCodes->purgeForUser($user);

        $this->assertSame(0, $this->backupCodesRemaining($user->id));
    }

    private function backupCodesRemaining(int $userId): int
    {
        return $this->backupCodes->where('user_id', $userId)->countAllResults();
    }
}

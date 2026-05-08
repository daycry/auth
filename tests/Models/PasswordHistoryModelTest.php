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

use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PasswordHistoryModelTest extends DatabaseTestCase
{
    private PasswordHistoryModel $history;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = model(PasswordHistoryModel::class);
    }

    public function testRecordHashSkipsWhenRetainIsZero(): void
    {
        $user = fake(UserModel::class);

        $this->history->recordHash($user, '$2y$10$abcdef', 0);

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testRecordHashSkipsWhenHashIsEmpty(): void
    {
        $user = fake(UserModel::class);

        $this->history->recordHash($user, '', 5);

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testRecordHashInsertsRow(): void
    {
        $user = fake(UserModel::class);

        $this->history->recordHash($user, '$2y$10$first', 5);

        $this->assertSame(1, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testRecordHashPrunesOlderEntriesBeyondRetention(): void
    {
        $user = fake(UserModel::class);

        // Insert 5 entries, retention = 3 → only the last 3 should remain.
        for ($i = 1; $i <= 5; $i++) {
            $this->history->recordHash($user, '$2y$10$hash' . $i, 3);
        }

        $this->assertSame(3, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testMatchesRecentReturnsFalseWhenRetainIsZero(): void
    {
        $user = fake(UserModel::class);

        $this->assertFalse($this->history->matchesRecent($user, 'pwd', 0));
    }

    public function testMatchesRecentReturnsFalseWhenPasswordIsEmpty(): void
    {
        $user = fake(UserModel::class);

        $this->assertFalse($this->history->matchesRecent($user, '', 5));
    }

    public function testMatchesRecentReturnsTrueOnMatch(): void
    {
        $user = fake(UserModel::class);

        $password = 'super-secret-42';
        $hash     = password_hash($password, PASSWORD_DEFAULT);

        $this->history->recordHash($user, $hash, 5);

        $this->assertTrue($this->history->matchesRecent($user, $password, 5));
    }

    public function testMatchesRecentReturnsFalseForDifferentPassword(): void
    {
        $user = fake(UserModel::class);

        $hash = password_hash('original-password', PASSWORD_DEFAULT);
        $this->history->recordHash($user, $hash, 5);

        $this->assertFalse($this->history->matchesRecent($user, 'different-password', 5));
    }

    public function testMatchesRecentScansOnlyTheRetainNewestRows(): void
    {
        $user = fake(UserModel::class);

        $oldHash = password_hash('very-old-password', PASSWORD_DEFAULT);
        $this->history->recordHash($user, $oldHash, 10);

        // Push 5 newer hashes; with retain=2 the old one is pruned.
        for ($i = 1; $i <= 5; $i++) {
            $this->history->recordHash($user, password_hash('new' . $i, PASSWORD_DEFAULT), 2);
        }

        // Even with retain=2, the very-old-password is gone (pruned).
        $this->assertFalse($this->history->matchesRecent($user, 'very-old-password', 2));
    }

    public function testPurgeForUserDeletesAllRows(): void
    {
        $user = fake(UserModel::class);

        for ($i = 1; $i <= 3; $i++) {
            $this->history->recordHash($user, '$2y$10$h' . $i, 5);
        }

        $this->history->purgeForUser($user);

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }
}

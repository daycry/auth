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

namespace Tests\Services;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Services\PasswordChangeRecorder;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PasswordChangeRecorderTest extends DatabaseTestCase
{
    private PasswordChangeRecorder $recorder;
    private PasswordHistoryModel $history;
    private UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder  = new PasswordChangeRecorder();
        $this->history   = model(PasswordHistoryModel::class);
        $this->userModel = model(UserModel::class);
    }

    public function testHistoryDisabledByDefault(): void
    {
        // passwordHistorySize defaults to 0 → no row should be inserted.
        $user = fake(UserModel::class);

        $this->recorder->record($user, '$2y$10$abcdefgh');

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testRecordWritesPreviousHashWhenHistoryEnabled(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 5]);

        $user = fake(UserModel::class);

        $this->recorder->record($user, '$2y$10$first-hash');
        $this->recorder->record($user, '$2y$10$second-hash');

        $this->assertSame(2, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testNullPreviousHashDoesNotInsert(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 5]);

        $user = fake(UserModel::class);

        $this->recorder->record($user, null);

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testEmptyPreviousHashDoesNotInsert(): void
    {
        $this->injectMockAttributesSecurity(['passwordHistorySize' => 5]);

        $user = fake(UserModel::class);

        $this->recorder->record($user, '');

        $this->assertSame(0, $this->history->where('user_id', $user->id)->countAllResults());
    }

    public function testStampsPasswordChangedAtOnUser(): void
    {
        $user = fake(UserModel::class);

        $this->recorder->record($user, '$2y$10$prev');

        // Read the column directly to bypass any entity-level filtering.
        $row = $this->userModel->builder()
            ->select('password_changed_at')
            ->where('id', $user->id)
            ->get()
            ->getRow();

        $this->assertNotNull($row->password_changed_at);
    }

    public function testNonPositiveUserIdSkipsTimestampStamp(): void
    {
        // No exception should escape; the recorder swallows any error.
        $detached     = new User();
        $detached->id = 0;

        $this->recorder->record($detached, null);

        // Reaching this assertion means no exception propagated; explicit
        // expectation lets PHPStan see the test isn't a tautology.
        $this->expectNotToPerformAssertions();
    }
}

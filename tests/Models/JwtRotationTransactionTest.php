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

use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Models\JwtTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class JwtRotationTransactionTest extends DatabaseTestCase
{
    public function testSuccessorFailureRollsBackTheConsumedPredecessor(): void
    {
        $user       = fake(UserModel::class);
        $repository = service('jwtTokenRepository');
        $expires    = date('Y-m-d H:i:s', time() + 3600);
        $repository->createRefreshToken((int) $user->id, 'original', $expires);
        $model = new class () extends UserIdentityModel {
            public function create($data): void
            {
                throw new RuntimeException('successor persistence failed');
            }
        };
        $failing = new JwtTokenRepository($model);

        try {
            $failing->rotateRefreshToken((int) $user->id, 'original', 'replacement', $expires);
            $this->fail('Expected successor failure');
        } catch (RuntimeException $e) {
            $this->assertSame('successor persistence failed', $e->getMessage());
        }
        $this->assertInstanceOf(UserIdentity::class, $repository->getRefreshToken((int) $user->id, 'original'));
        $this->assertNull($repository->getRefreshToken((int) $user->id, 'replacement'));
    }
}

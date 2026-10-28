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

use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\WebAuthnCredentialRepositoryInterface;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebAuthnContractsTest extends DatabaseTestCase
{
    public function testEnrollmentStateUsesTheReplaceableRepository(): void
    {
        $repository = $this->createMock(WebAuthnCredentialRepositoryInterface::class);
        $repository->expects($this->once())->method('countActiveForUser')->with(42)->willReturn(2);
        Services::injectMock('webAuthnCredentialRepository', $repository);

        $this->assertTrue((new User(['id' => 42]))->hasWebAuthnCredentials());
    }
}

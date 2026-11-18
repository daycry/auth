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

namespace Tests\Authentication\Services;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Services\MfaPolicy;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MfaPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        setting('AuthSecurity.mfaRequired', false);
        setting('AuthSecurity.mfaRequiredGroups', []);
        setting('AuthSecurity.mfaExemptGroups', []);
        setting('AuthSecurity.mfaGracePeriod', 0);
        setting('AuthSecurity.mfaEnforcementStartedAt', 0);
    }

    public function testDisabledPolicyDoesNotRequireEnrollment(): void
    {
        $policy = new MfaPolicy();
        $this->assertFalse($policy->requires($this->user()));
    }

    public function testRequiredGroupsAndExemptionsAreEvaluatedPerUser(): void
    {
        setting('AuthSecurity.mfaRequiredGroups', ['admin']);
        $policy = new MfaPolicy();
        $this->assertTrue($policy->requires($this->user(['admin'])));
        $this->assertFalse($policy->requires($this->user(['member'])));
        setting('AuthSecurity.mfaExemptGroups', ['service']);
        $this->assertFalse($policy->requires($this->user(['admin', 'service'])));
    }

    public function testGraceUsesFixedRolloutAndCannotBeRestartedByAnotherSession(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        setting('AuthSecurity.mfaGracePeriod', 600);
        setting('AuthSecurity.mfaEnforcementStartedAt', time() - 900);
        $policy = new MfaPolicy();
        $user   = $this->user();
        $this->assertTrue($policy->enrollmentRequired($user));
        $_SESSION = [];
        $this->assertTrue($policy->enrollmentRequired($user));
        setting('AuthSecurity.mfaEnforcementStartedAt', time() - 60);
        $this->assertTrue($policy->withinGrace($user));
        $this->assertFalse($policy->enrollmentRequired($user));
    }

    public function testProofIsBoundToCurrentUserAndRejectsFutureTimestamps(): void
    {
        $policy = new MfaPolicy();
        $user   = $this->user();
        $this->assertFalse($policy->verified($user));
        $policy->markVerified($user);
        $this->assertTrue($policy->verified($user));
        $other     = $this->user();
        $other->id = 42;
        $this->assertFalse($policy->verified($other));
        session()->set(MfaPolicy::PROOF_AT_KEY, time() + 60);
        $this->assertFalse($policy->verified($user));
    }

    public function testEnrolledFactorSatisfiesEnrollmentButNotProof(): void
    {
        setting('AuthSecurity.mfaRequired', true);
        $policy = new MfaPolicy();
        $user   = $this->user([], true);
        $this->assertTrue($policy->hasFactor($user));
        $this->assertFalse($policy->enrollmentRequired($user));
        $this->assertFalse($policy->verified($user));
    }

    private function user(array $groups = [], bool $totp = false, bool $passkey = false): User
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['inGroup', 'hasTotpEnabled', 'hasWebAuthnCredentials'])
            ->getMock();
        $user->id         = 41;
        $user->created_at = '2020-01-01 00:00:00';
        $user->method('inGroup')->willReturnCallback(static fn (string ...$requested): bool => array_intersect($requested, $groups) !== []);
        $user->method('hasTotpEnabled')->willReturn($totp);
        $user->method('hasWebAuthnCredentials')->willReturn($passkey);

        return $user;
    }
}

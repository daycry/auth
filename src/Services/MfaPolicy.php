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

namespace Daycry\Auth\Services;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\MfaPolicyInterface;

/**
 * Enrollment requirements and proofs of a second factor for this session.
 */
class MfaPolicy implements MfaPolicyInterface
{
    public const PROOF_USER_KEY = 'auth_mfa_user';
    public const PROOF_AT_KEY   = 'auth_mfa_at';

    /**
     * {@inheritDoc}
     */
    public function requires(User $user): bool
    {
        $exempt = (array) (setting('AuthSecurity.mfaExemptGroups') ?? []);
        if ($exempt !== [] && $user->inGroup(...$exempt)) {
            return false;
        }

        $groups = (array) (setting('AuthSecurity.mfaRequiredGroups') ?? []);

        return (bool) (setting('AuthSecurity.mfaRequired') ?? false)
            || ($groups !== [] && $user->inGroup(...$groups));
    }

    /**
     * {@inheritDoc}
     */
    public function hasFactor(User $user): bool
    {
        return $user->hasTotpEnabled()
            || ((bool) (setting('AuthSecurity.webauthnEnabled') ?? false) && $user->hasWebAuthnCredentials());
    }

    /**
     * {@inheritDoc}
     */
    public function withinGrace(User $user): bool
    {
        $grace = max(0, (int) (setting('AuthSecurity.mfaGracePeriod') ?? 0));
        if ($grace === 0) {
            return false;
        }

        $createdAt = $user->created_at;
        $created   = $createdAt === null ? 0 : Time::parse((string) $createdAt)->getTimestamp();
        $rollout   = (int) (setting('AuthSecurity.mfaEnforcementStartedAt') ?? 0);
        $started   = max($created, $rollout);

        return $started > 0 && time() < $started + $grace;
    }

    /**
     * {@inheritDoc}
     */
    public function enrollmentRequired(User $user): bool
    {
        return $this->requires($user) && ! $this->hasFactor($user) && ! $this->withinGrace($user);
    }

    /**
     * {@inheritDoc}
     */
    public function verified(User $user): bool
    {
        $at = (int) session(self::PROOF_AT_KEY);

        return (string) session(self::PROOF_USER_KEY) === (string) $user->id
            && $at > 0 && $at <= time();
    }

    /**
     * {@inheritDoc}
     */
    public function markVerified(User $user): void
    {
        session()->set(self::PROOF_USER_KEY, (string) $user->id);
        session()->set(self::PROOF_AT_KEY, time());
    }
}

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

namespace Daycry\Auth\Models;

use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Enums\IdentityType;
use Daycry\Auth\Exceptions\DatabaseException;
use Daycry\Auth\Interfaces\JwtTokenRepositoryInterface;
use Daycry\Auth\Services\AuditLogger;
use Throwable;

/**
 * Repository for JWT refresh token operations.
 *
 * Encapsulates JWT refresh token CRUD from UserIdentityModel.
 */
class JwtTokenRepository implements JwtTokenRepositoryInterface
{
    public function __construct(
        private readonly UserIdentityModel $identityModel,
    ) {
    }

    /**
     * Stores a new JWT refresh token for the given user.
     *
     * @param int    $userId    User primary key
     * @param string $rawToken  The raw (unhashed) token to store
     * @param string $expiresAt Datetime string 'Y-m-d H:i:s'
     */
    public function createRefreshToken(int $userId, string $rawToken, string $expiresAt, bool $mfaVerified = false): void
    {
        $this->storeRefreshToken($userId, $rawToken, $expiresAt, bin2hex(random_bytes(32)), $mfaVerified);
    }

    /**
     * Store a hashed token in the same family as its predecessor.
     */
    private function storeRefreshToken(int $userId, string $rawToken, string $expiresAt, string $family, bool $mfaVerified = false): void
    {
        $this->identityModel->create([
            'user_id'        => $userId,
            'type'           => IdentityType::JWT_REFRESH->value,
            'secret'         => hash('sha256', $rawToken),
            'expires'        => $expiresAt,
            'refresh_family' => $family,
            'extra'          => json_encode(['mfa_verified' => $mfaVerified], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Consume and replace in one transaction. A conditional UPDATE grants
     * exactly one request the rotation; a replay invalidates the family.
     * Replays are looked up even after revocation, until expired rows are purged.
     */
    public function rotateRefreshToken(int $userId, string $rawToken, string $replacement, string $expiresAt): bool
    {
        $db  = $this->identityModel->builder()->db();
        $now = Time::now()->format('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $identity = $this->identityModel->where('user_id', $userId)
                ->where('type', IdentityType::JWT_REFRESH->value)
                ->where('secret', hash('sha256', $rawToken))->first();
            if ($identity === null) {
                $db->transCommit();

                return false;
            }
            $family = (string) ($identity->refresh_family ?? '');
            // Derive legacy family identity without rewriting a token before
            // obtaining its rotation claim; concurrent requests derive the same value.
            if ($family === '') {
                $family = hash('sha256', 'legacy:' . $userId . ':' . $identity->id);
            }
            $builder = $this->identityModel->builder();
            $ok      = $builder->where('id', $identity->id)->where('revoked_at')
                ->where('expires >', $now)
                ->update(['revoked_at' => $now, 'refresh_family' => $family]);
            if ($ok === false) {
                throw new DatabaseException('Refresh-token rotation failed.');
            }
            if ($db->affectedRows() !== 1) {
                // Only possession of a known token can trigger family revocation.
                if ($identity->revoked_at !== null || (string) $identity->expires > $now) {
                    $builder->where('user_id', $userId)->where('type', IdentityType::JWT_REFRESH->value)
                        ->where('refresh_family', $family)->update(['revoked_at' => $now]);
                    service('auditLogger')->record(AuditLogger::EVENT_REFRESH_TOKEN_REVOKED, $userId, ['reason' => 'reuse', 'family' => $family]);
                }
                if (! $db->transStatus()) {
                    throw new DatabaseException('Refresh-token family revocation failed.');
                }
                $db->transCommit();

                return false;
            }
            $metadata = json_decode((string) $identity->extra, true);
            $this->storeRefreshToken($userId, $replacement, $expiresAt, $family, is_array($metadata) && ($metadata['mfa_verified'] ?? false) === true);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new DatabaseException('Refresh-token rotation could not commit.');
            }

            return true;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * Finds a valid (non-expired, non-revoked) JWT refresh token.
     *
     * @param int    $userId   User primary key
     * @param string $rawToken The raw (unhashed) token
     */
    public function getRefreshToken(int $userId, string $rawToken): ?UserIdentity
    {
        return $this->identityModel->getJwtRefreshToken($userId, $rawToken);
    }

    /**
     * Revokes a JWT refresh token (hard-delete).
     *
     * @param int $identityId The identity record primary key
     */
    public function revokeRefreshToken(int $identityId): void
    {
        $this->identityModel->delete($identityId);
    }

    /**
     * Soft-revokes a JWT refresh token by setting revoked_at.
     *
     * @param int         $identityId The identity record primary key
     * @param int|null    $userId     User id for audit (when known by the caller)
     * @param string|null $reason     Optional reason recorded in the audit log
     */
    public function softRevokeRefreshToken(int $identityId, ?int $userId = null, ?string $reason = null): void
    {
        $this->identityModel->revokeIdentityById($identityId);

        $metadata = ['identity_id' => $identityId];

        if ($reason !== null) {
            $metadata['reason'] = $reason;
        }

        service('auditLogger')->record(AuditLogger::EVENT_REFRESH_TOKEN_REVOKED, $userId, $metadata);
    }

    /**
     * Soft-revokes every outstanding JWT refresh token of a user, so none of
     * them can mint new access tokens (ban, password change).
     *
     * @param int         $userId User primary key
     * @param string|null $reason Optional reason recorded in the audit log
     */
    public function softRevokeAllRefreshTokens(int $userId, ?string $reason = null): void
    {
        $this->identityModel->revokeIdentitiesByUserAndType($userId, IdentityType::JWT_REFRESH->value);

        $metadata = ['scope' => 'all'];

        if ($reason !== null) {
            $metadata['reason'] = $reason;
        }

        service('auditLogger')->record(AuditLogger::EVENT_REFRESH_TOKEN_REVOKED, $userId, $metadata);
    }
}

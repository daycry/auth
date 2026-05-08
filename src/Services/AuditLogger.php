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

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Models\AuditLogModel;
use Throwable;

/**
 * Records granular account/security events to the auth_audit_logs table.
 *
 * Hook points fire calls to {@see record()} from sensitive flows:
 *   - 2FA enable/disable
 *   - password change, email change, role change
 *   - lockout triggered
 *   - token revoke (access / JWT refresh)
 *   - trusted device add/remove
 *
 * Failures (DB unavailable, schema missing) are caught and logged at
 * `warning` level — audit failure must never break the user-facing flow.
 */
class AuditLogger
{
    /**
     * Canonical event type identifiers. Using constants prevents typos in
     * caller code and keeps the vocabulary discoverable.
     */
    public const EVENT_TOTP_ENABLED = 'totp.enabled';

    public const EVENT_TOTP_DISABLED          = 'totp.disabled';
    public const EVENT_TOTP_ADMIN_RESET       = 'totp.admin_reset';
    public const EVENT_PASSWORD_CHANGED       = 'password.changed';
    public const EVENT_PASSWORD_RESET         = 'password.reset';
    public const EVENT_PASSWORD_CONFIRMED     = 'password.confirmed';
    public const EVENT_EMAIL_CHANGE_REQUEST   = 'email.change_request';
    public const EVENT_EMAIL_CHANGED          = 'email.changed';
    public const EVENT_USER_LOCKED            = 'user.locked';
    public const EVENT_USER_UNLOCKED          = 'user.unlocked';
    public const EVENT_GROUP_ASSIGNED         = 'group.assigned';
    public const EVENT_GROUP_REVOKED          = 'group.revoked';
    public const EVENT_PERMISSION_GRANTED     = 'permission.granted';
    public const EVENT_PERMISSION_REVOKED     = 'permission.revoked';
    public const EVENT_TOKEN_REVOKED          = 'token.revoked';
    public const EVENT_REFRESH_TOKEN_REVOKED  = 'token.refresh_revoked';
    public const EVENT_TRUSTED_DEVICE_ADDED   = 'device.trusted_added';
    public const EVENT_TRUSTED_DEVICE_REMOVED = 'device.trusted_removed';
    public const EVENT_OAUTH_LINKED           = 'oauth.linked';
    public const EVENT_OAUTH_UNLINKED         = 'oauth.unlinked';
    public const EVENT_SUSPICIOUS_LOGIN       = 'login.suspicious';
    public const EVENT_USER_ANONYMIZED        = 'user.anonymized';

    /**
     * Records a single audit event.
     *
     * @param string               $eventType One of the EVENT_* constants (or a custom snake_case identifier).
     * @param int|null             $userId    Affected user id.
     * @param array<string, mixed> $metadata  Extra context (will be JSON-encoded).
     * @param int|null             $actorId   User id that triggered the event when different from $userId
     *                                        (admin actions). Defaults to $userId.
     */
    public function record(string $eventType, ?int $userId, array $metadata = [], ?int $actorId = null): void
    {
        try {
            /** @var AuditLogModel $model */
            $model = model(AuditLogModel::class);

            [$ip, $ua] = $this->resolveRequestContext();

            $model->insert([
                'user_id'    => $userId,
                'actor_id'   => $actorId ?? $userId,
                'event_type' => $eventType,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'metadata'   => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => Time::now()->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            log_message('warning', 'AuditLogger::record failed for {event}: {message}', [
                'event'   => $eventType,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveRequestContext(): array
    {
        $ip = null;
        $ua = null;

        try {
            $request = service('request');

            if ($request instanceof IncomingRequest) {
                $ip = $request->getIPAddress();
                $ua = (string) $request->getUserAgent();

                if ($ua === '') {
                    $ua = null;
                }
            }
        } catch (Throwable) {
            // CLI context or service container not ready — fine to skip.
        }

        return [$ip, $ua];
    }
}

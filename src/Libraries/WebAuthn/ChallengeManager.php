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

namespace Daycry\Auth\Libraries\WebAuthn;

use CodeIgniter\I18n\Time;

/**
 * Per-ceremony WebAuthn state, stored in the PHP session. A stored entry holds
 * the serialized options the crypto library needs to validate, plus the
 * ceremony type, an optional bound user id, and a creation timestamp. Entries
 * are single-use (deleted on pull) and TTL-bounded.
 */
class ChallengeManager
{
    private const SESSION_KEY = '_webauthn_ceremony';
    private const LABEL_KEY   = '_webauthn_label';

    /**
     * @param '2fa'|'login'|'register' $type
     */
    public function store(string $type, string $optionsJson, int|string|null $userId = null): void
    {
        session()->set(self::SESSION_KEY, [
            'type'       => $type,
            'options'    => $optionsJson,
            'user_id'    => $userId,
            'created_at' => Time::now()->getTimestamp(),
        ]);
    }

    /**
     * Returns the stored entry and deletes it (single-use) when it matches the
     * expected type/user and has not expired; null otherwise.
     *
     * @param '2fa'|'login'|'register' $type
     *
     * @return array{type: string, options: string, user_id: int|string|null, created_at: int}|null
     */
    public function pull(string $type, int|string|null $userId = null): ?array
    {
        /** @var array{type: string, options: string, user_id: int|string|null, created_at: int}|null $entry */
        $entry = session()->get(self::SESSION_KEY);
        session()->remove(self::SESSION_KEY);

        if ($entry === null || $entry['type'] !== $type) {
            return null;
        }

        if ($userId !== null && (string) ($entry['user_id'] ?? '') !== (string) $userId) {
            return null;
        }

        $ttl = (int) (setting('AuthSecurity.webauthnChallengeTtl') ?? 120);
        if (Time::now()->getTimestamp() - (int) $entry['created_at'] >= $ttl) {
            return null;
        }

        return $entry;
    }

    public function stashLabel(?string $label): void
    {
        session()->set(self::LABEL_KEY, $label);
    }

    public function pullLabel(): ?string
    {
        /** @var string|null $label */
        $label = session()->get(self::LABEL_KEY);
        session()->remove(self::LABEL_KEY);

        return $label;
    }
}

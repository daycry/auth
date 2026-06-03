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

use CodeIgniter\Model;
use Daycry\Auth\Entities\WebAuthnCredential;

class WebAuthnCredentialModel extends Model
{
    protected $primaryKey     = 'id';
    protected $returnType     = WebAuthnCredential::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'uuid', 'user_id', 'credential_id', 'credential', 'user_handle',
        'name', 'sign_count', 'transports', 'aaguid', 'last_used_at', 'revoked_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $beforeInsert  = ['generateUuid'];

    /**
     * @var array<string, string>
     */
    protected array $tables;

    protected function initialize(): void
    {
        parent::initialize();
        $this->tables = config('Auth')->tables;
        $this->table  = $this->tables['webauthn_credentials'];
    }

    /**
     * @param array{data: array<string, mixed>} $data
     *
     * @return array{data: array<string, mixed>}
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = service('uuid')->uuid7()->toRfc4122();
        }

        return $data;
    }

    public function firstActiveByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        /** @var WebAuthnCredential|null $row */
        $row = $this->where('credential_id', $credentialId)
            ->where('revoked_at')
            ->first();

        return $row;
    }

    /**
     * Whether a row with this credential id exists, regardless of revoked
     * state. The UNIQUE constraint on credential_id ignores revoked_at, so a
     * pre-insert duplicate check must consider revoked rows too.
     */
    public function existsByCredentialId(string $credentialId): bool
    {
        return $this->where('credential_id', $credentialId)
            ->countAllResults() > 0;
    }

    /**
     * @return list<WebAuthnCredential>
     */
    public function activeForUser(int|string $userId): array
    {
        /** @var list<WebAuthnCredential> $rows */
        $rows = $this->where('user_id', $userId)
            ->where('revoked_at')
            ->orderBy('id', 'ASC')
            ->findAll();

        return $rows;
    }

    public function countActiveForUser(int|string $userId): int
    {
        return $this->where('user_id', $userId)
            ->where('revoked_at')
            ->countAllResults();
    }
}

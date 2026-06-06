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

namespace Daycry\Auth\Database\Migrations;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;
use CodeIgniter\Exceptions\RuntimeException;
use Config\Database;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Config\Auth;

/**
 * Normalizes EXISTING login identifiers to lowercase so that legacy
 * mixed-case accounts keep working now that the login query no longer
 * applies LOWER() at read time.
 *
 * Scope (STRICT): only `users.username` and the email_password identity
 * `secret` (the stored email). OAuth social ids, access-token / JWT-refresh
 * hashes, ephemeral codes (magic-link, email-2fa, etc.), TOTP secrets and
 * WebAuthn credentials are NEVER touched.
 *
 * Safety contract:
 *  - DETECT first (read-only). If lowercasing would collapse two distinct
 *    rows into the same key (a case-only duplicate), the migration ABORTS
 *    with an actionable report and performs ZERO writes. Accounts are never
 *    merged or deleted — the operator must resolve duplicates manually and
 *    re-run.
 *  - Detection of BOTH tables happens before any mutation, so a collision in
 *    either table prevents writes to either.
 *  - The mutation phase is transaction-wrapped and compares case in PHP
 *    (collation-proof: avoids false-negatives under case-insensitive
 *    collations).
 */
class NormalizeLoginIdentifiers extends Migration
{
    /**
     * Rows are scanned/updated in chunks to keep memory bounded and to avoid
     * one UPDATE per row on large tables (mirrors the uuid backfill migration).
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Auth table names, keyed by logical name.
     */
    private array $tables;

    /**
     * Concrete DB connection (typed; Migration::$db is ConnectionInterface).
     */
    private readonly BaseConnection $connection;

    /**
     * @param Forge|null $forge Optional forge instance (injected in tests).
     */
    public function __construct(?Forge $forge = null)
    {
        /** @var Auth $authConfig */
        $authConfig = config('Auth');

        if ($authConfig->DBGroup !== null) {
            $this->DBGroup = $authConfig->DBGroup;
        }

        parent::__construct($forge);

        $this->connection = Database::connect($authConfig->DBGroup);
        $this->tables     = $authConfig->tables;
    }

    /**
     * Phase 1 detects case-collisions on both targets (and aborts if any
     * exist), then Phase 2 lowercases the remaining mixed-case rows inside a
     * transaction.
     */
    public function up(): void
    {
        // Phase 1 — DETECT (read-only, BEFORE any write).
        // Check BOTH tables before mutating anything so a collision in either
        // table guarantees zero writes.
        $this->assertNoCollision($this->tables['users'], 'username', 'username IS NOT NULL', null);
        $this->assertNoCollision($this->tables['identities'], 'secret', 'type', Session::ID_TYPE_EMAIL_PASSWORD);

        // Phase 2 — MUTATE (only reached if Phase 1 found nothing).
        $this->connection->transStart();

        $this->normalizeUsernames();
        $this->normalizeEmailSecrets();

        // With CI4 defaults a failed UPDATE inside the transaction does not
        // throw — it silently rolls back and transComplete() returns false.
        // Surface that so the migration is never recorded as "applied" while
        // rows were left un-normalized (which would break index-friendly login).
        if ($this->connection->transComplete() === false) {
            throw new RuntimeException(
                'Login-identifier normalization failed and was rolled back; no rows were changed. '
                . 'Re-run the migration once the database error is resolved.',
            );
        }
    }

    /**
     * The original (mixed-)case of each identifier is unrecoverable once it
     * has been lowercased, so the rollback is intentionally a no-op. There is
     * no correct way to "un-lowercase" `Mixed@Example.com` from `mixed@…`.
     */
    public function down(): void
    {
        // Intentionally empty — see DocBlock above.
    }

    /**
     * Detects case-only duplicate collisions on $column within the rows
     * matched by the WHERE clause ($whereField op $whereValue). Throws a
     * RuntimeException listing the offending rows when any collision is found,
     * without performing any write.
     *
     * When $whereValue is null, $whereField is treated as a raw condition
     * string (e.g. 'username IS NOT NULL'); otherwise it is an equality match
     * (e.g. type = 'email_password').
     */
    private function assertNoCollision(string $table, string $column, string $whereField, ?string $whereValue): void
    {
        // Portable collision query (no GROUP_CONCAT / string_agg):
        //   SELECT LOWER(col) AS k, COUNT(*) AS c
        //   FROM {table} WHERE <scope>
        //   GROUP BY LOWER(col) HAVING COUNT(*) > 1
        $collisions = $this->scopedBuilder($table, $whereField, $whereValue)
            ->select('LOWER(' . $column . ') AS k, COUNT(*) AS c', false)
            ->groupBy('LOWER(' . $column . ')', false)
            ->having('COUNT(*) > 1', null, false)
            ->get()
            ->getResultArray();

        if ($collisions === []) {
            return;
        }

        $lines = [];

        foreach ($collisions as $collision) {
            $key = (string) $collision['k'];

            $rows = $this->scopedBuilder($table, $whereField, $whereValue)
                ->select('id, ' . $column)
                ->where('LOWER(' . $column . ') =', $key, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $details = [];

            foreach ($rows as $row) {
                $details[] = sprintf('id=%s (%s)', (string) $row['id'], (string) $row[$column]);
            }

            $lines[] = sprintf('  - lowercased "%s": %s', $key, implode(', ', $details));
        }

        throw new RuntimeException(sprintf(
            "Cannot normalize %s.%s to lowercase: case-only duplicate(s) detected.\n%s\n"
            . 'Resolve these duplicates manually (accounts are never merged or deleted), then re-run the migration.',
            $table,
            $column,
            implode("\n", $lines),
        ));
    }

    /**
     * Returns a fresh query builder for $table scoped to the rows of interest.
     *
     * When $whereValue is null, $whereField is applied as a raw condition
     * (e.g. 'username IS NOT NULL'); otherwise it is an equality match
     * (e.g. type = 'email_password').
     */
    private function scopedBuilder(string $table, string $whereField, ?string $whereValue): BaseBuilder
    {
        $builder = $this->connection->table($table);

        if ($whereValue === null) {
            $builder->where($whereField);
        } else {
            $builder->where($whereField, $whereValue);
        }

        return $builder;
    }

    /**
     * Lowercases `users.username` for any row whose username is not already
     * lowercase. Comparison is performed in PHP so it is correct regardless
     * of the database collation.
     */
    private function normalizeUsernames(): void
    {
        $offset = 0;

        while (true) {
            $rows = $this->connection->table($this->tables['users'])
                ->select('id, username')
                ->where('username IS NOT NULL')
                ->orderBy('id', 'ASC')
                ->get(self::CHUNK_SIZE, $offset)
                ->getResultArray();

            if ($rows === []) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $username = (string) $row['username'];
                $lower    = strtolower($username);

                if ($username !== $lower) {
                    $batch[] = ['id' => $row['id'], 'username' => $lower];
                }
            }

            if ($batch !== []) {
                $this->connection->table($this->tables['users'])->updateBatch($batch, 'id');
            }

            if (count($rows) < self::CHUNK_SIZE) {
                break;
            }

            $offset += self::CHUNK_SIZE;
        }
    }

    /**
     * Lowercases the email_password identity `secret` (the stored email) for
     * any row whose secret is not already lowercase. The `type` filter is
     * strictly `email_password` (Session::ID_TYPE_EMAIL_PASSWORD) so no other
     * identity type is affected. Comparison is performed in PHP (collation-proof).
     */
    private function normalizeEmailSecrets(): void
    {
        $offset = 0;

        while (true) {
            $rows = $this->connection->table($this->tables['identities'])
                ->select('id, secret')
                ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
                ->orderBy('id', 'ASC')
                ->get(self::CHUNK_SIZE, $offset)
                ->getResultArray();

            if ($rows === []) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $secret = (string) $row['secret'];
                $lower  = strtolower($secret);

                if ($secret !== $lower) {
                    $batch[] = ['id' => $row['id'], 'secret' => $lower];
                }
            }

            if ($batch !== []) {
                $this->connection->table($this->tables['identities'])->updateBatch($batch, 'id');
            }

            if (count($rows) < self::CHUNK_SIZE) {
                break;
            }

            $offset += self::CHUNK_SIZE;
        }
    }
}

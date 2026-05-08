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

namespace Daycry\Auth\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Models\AuditLogModel;
use Daycry\Auth\Models\UserModel;
use InvalidArgumentException;
use Throwable;

/**
 * Reads recent entries from the audit log table.
 *
 * Usage:
 *   php spark auth:audit
 *   php spark auth:audit --since=24h
 *   php spark auth:audit --user=user@example.com
 *   php spark auth:audit --type=totp.enabled
 *   php spark auth:audit --limit=50
 */
class AuditCommand extends BaseCommand
{
    protected $name        = 'auth:audit';
    protected $description = 'Show recent entries from the audit log.';
    protected $usage       = 'auth:audit [--since=24h] [--user=<email>] [--type=<event_type>] [--limit=<n>]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--since' => 'Time window, e.g. 24h, 7d, 30d (default: 7d).',
        '--user'  => 'Filter by user email.',
        '--type'  => 'Filter by event_type (e.g. totp.enabled).',
        '--limit' => 'Maximum number of rows to display (default: 100, max: 500).',
    ];

    public function run(array $params): int
    {
        $since = (string) ($params['since'] ?? '7d');
        $email = (string) ($params['user'] ?? '');
        $type  = (string) ($params['type'] ?? '');
        $limit = max(1, min(500, (int) ($params['limit'] ?? 100)));

        try {
            $cutoff = $this->parseSince($since);
        } catch (Throwable $e) {
            $this->error('Invalid --since value: ' . $e->getMessage());

            return 1;
        }

        try {
            /** @var AuditLogModel $auditModel */
            $auditModel = model(AuditLogModel::class);

            $builder = $auditModel
                ->where('created_at >=', $cutoff->toDateTimeString())
                ->orderBy('id', 'DESC')
                ->limit($limit);

            if ($type !== '') {
                $builder = $builder->where('event_type', $type);
            }

            if ($email !== '') {
                /** @var UserModel $userModel */
                $userModel = model(UserModel::class);
                $user      = $userModel->findByCredentials(['email' => $email]);

                if ($user === null) {
                    $this->error('User not found: ' . $email);

                    return 1;
                }

                $builder = $builder->where('user_id', $user->id);
            }

            $rows = $builder->find();
        } catch (Throwable $e) {
            $this->error('Audit query failed: ' . $e->getMessage());

            return 1;
        }

        if ($rows === []) {
            $this->write('No audit entries match the filters.', 'yellow');

            return 0;
        }

        $head = ['ID', 'When', 'Event', 'User', 'IP', 'Metadata'];
        $body = [];

        foreach ($rows as $row) {
            $userId  = is_object($row) ? ($row->user_id ?? null) : ($row['user_id'] ?? null);
            $event   = is_object($row) ? ($row->event_type ?? '') : ($row['event_type'] ?? '');
            $created = is_object($row) ? ($row->created_at ?? '') : ($row['created_at'] ?? '');
            $ip      = is_object($row) ? ($row->ip_address ?? '') : ($row['ip_address'] ?? '');
            $rawMeta = is_object($row) ? ($row->metadata ?? null) : ($row['metadata'] ?? null);
            $rowId   = is_object($row) ? ($row->id ?? '') : ($row['id'] ?? '');

            $body[] = [
                (string) $rowId,
                (string) $created,
                (string) $event,
                $userId === null ? '' : (string) $userId,
                (string) $ip,
                is_string($rawMeta) ? $this->shortMeta($rawMeta) : '',
            ];
        }

        CLI::table($body, $head);

        return 0;
    }

    /**
     * Parses values like `24h`, `7d`, `30d`, `1w` into an absolute Time.
     */
    private function parseSince(string $expr): Time
    {
        $expr = strtolower(trim($expr));

        if ($expr === '' || ! preg_match('/^(\d+)([smhdw])$/', $expr, $m)) {
            throw new InvalidArgumentException("expected NNs|m|h|d|w, got '{$expr}'");
        }

        $n   = (int) $m[1];
        $now = Time::now();

        return match ($m[2]) {
            's'     => $now->subSeconds($n),
            'm'     => $now->subMinutes($n),
            'h'     => $now->subHours($n),
            'd'     => $now->subDays($n),
            'w'     => $now->subDays($n * 7),
            default => $now->subDays(7),
        };
    }

    /**
     * Truncates a metadata JSON string to a single line for tabular display.
     */
    private function shortMeta(string $json): string
    {
        $compact = preg_replace('/\s+/', ' ', $json) ?? $json;

        return mb_strlen($compact) > 60
            ? mb_substr($compact, 0, 57) . '...'
            : $compact;
    }
}

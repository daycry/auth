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

use Daycry\Auth\Entities\User;
use Throwable;

/**
 * GDPR-friendly data export and account anonymization.
 *
 * Usage:
 *   php spark auth:gdpr export -e <email> [-o <path>]
 *   php spark auth:gdpr anonymize -e <email>
 *
 * `export` writes a JSON document containing the user's row, identities
 * (with secrets redacted), device sessions, login history, audit log
 * entries, and password-history metadata.
 *
 * `anonymize` keeps the user row (preserving FK integrity) but replaces
 * personal fields with placeholders, deletes identities/tokens/device
 * sessions/password-history/backup-codes, and writes an audit-log entry.
 */
class GdprCommand extends BaseCommand
{
    protected $name        = 'auth:gdpr';
    protected $description = 'GDPR helpers: export user data / anonymize a user account.';
    protected $usage       = <<<'EOL'
        auth:gdpr export -e <email> [-o <path>]
        auth:gdpr anonymize -e <email>
        EOL;

    /**
     * @var array<string, string>
     */
    protected $options = [
        '-e' => 'Target user email.',
        '-i' => 'Target user id (alternative to -e).',
        '-o' => 'Output file path (export only). Defaults to stdout.',
    ];

    public function run(array $params): int
    {
        $action = $params[0] ?? '';
        $user   = $this->resolveTargetUser($params);

        if (! $user instanceof User) {
            return 1;
        }

        return match ($action) {
            'export'    => $this->exportAction($user, (string) ($params['o'] ?? '')),
            'anonymize' => $this->anonymizeAction($user),
            default     => $this->unsupported(),
        };
    }

    private function unsupported(): int
    {
        $this->error('Unsupported action. Supported: export, anonymize.');

        return 1;
    }

    private function exportAction(User $user, string $outputPath = ''): int
    {
        try {
            $payload = service('userDataExporter')->export($user);

            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            if ($json === false) {
                $this->error('JSON encoding failed.');

                return 1;
            }

            if ($outputPath !== '') {
                file_put_contents($outputPath, $json);
                $this->write('Wrote export to ' . $outputPath, 'green');
            } else {
                $this->write($json);
            }

            return 0;
        } catch (Throwable $e) {
            $this->error('Export failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function anonymizeAction(User $user): int
    {
        if ($this->prompt('This will permanently anonymize user ' . $user->id . '. Continue?', ['y', 'n']) !== 'y') {
            $this->write('Aborted.', 'yellow');

            return 0;
        }

        try {
            $userId = (int) $user->id;

            // Identities, sessions, passkeys, codes, history and login records
            // are removed and the row anonymised, in one transaction.
            service('userAnonymizer')->anonymize($user, 'cli');

            $this->write('User ' . $userId . ' anonymised. Identities and tokens removed.', 'green');

            return 0;
        } catch (Throwable $e) {
            $this->error('Anonymization failed: ' . $e->getMessage());

            return 1;
        }
    }
}

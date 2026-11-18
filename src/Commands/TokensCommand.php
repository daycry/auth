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
use Daycry\Auth\Entities\User;
use Throwable;

/**
 * Admin CLI to soft-revoke a user's access / JWT-refresh tokens.
 *
 * Usage:
 *   php spark auth:tokens revoke -e user@example.com
 *   php spark auth:tokens revoke -e user@example.com --type=access_token
 *   php spark auth:tokens revoke -e user@example.com --type=jwt_refresh
 *   php spark auth:tokens revoke -e user@example.com --type=all
 */
class TokensCommand extends BaseCommand
{
    /**
     * Command's name
     *
     * @var string
     */
    protected $name = 'auth:tokens';

    /**
     * Command's short description
     *
     * @var string
     */
    protected $description = 'Soft-revoke access / refresh tokens for a user.';

    /**
     * Command's usage
     *
     * @var string
     */
    protected $usage = <<<'EOL'
        auth:tokens revoke -e <email> [--type=access_token|jwt_refresh|all]
        EOL;

    /**
     * Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'action' => 'Currently only `revoke` is supported.',
    ];

    /**
     * Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-e'     => 'Target user email.',
        '-i'     => 'Target user id (alternative to -e).',
        '--type' => 'Token type to revoke: access_token | jwt_refresh | all (default: all).',
    ];

    public function run(array $params): int
    {
        $action = $params[0] ?? '';

        if ($action !== 'revoke') {
            $this->error('Unsupported action. Supported: revoke.');

            return 1;
        }

        $type = (string) ($params['type'] ?? 'all');

        if (! in_array($type, ['all', 'access_token', 'jwt_refresh'], true)) {
            $this->error('Unknown --type. Use: access_token | jwt_refresh | all.');

            return 1;
        }

        $user = $this->resolveTargetUser($params);

        if (! $user instanceof User) {
            return 1;
        }

        try {
            if ($type === 'all' || $type === 'access_token') {
                $repo = service('accessTokenRepository');
                $repo->softRevokeAllAccessTokens($user);
                $this->write('Revoked all access tokens for user ' . $user->id, 'green');
            }

            if ($type === 'all' || $type === 'jwt_refresh') {
                // Through the repository so the revocation is audited, like
                // the access-token branch above.
                service('jwtTokenRepository')->softRevokeAllRefreshTokens((int) $user->id, 'cli');
                $this->write('Revoked all JWT refresh tokens for user ' . $user->id, 'green');
            }
        } catch (Throwable $e) {
            $this->error('Token revocation failed: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }
}

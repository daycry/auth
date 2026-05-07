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

namespace Daycry\Auth\Authentication\Passwords;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\PasswordValidatorInterface;
use Daycry\Auth\Models\PasswordHistoryModel;
use Daycry\Auth\Result;

/**
 * Rejects passwords that match any of the user's recent password hashes
 * (configured via `AuthSecurity::$passwordHistorySize`).
 *
 * Add to `AuthSecurity::$passwordValidators` to enable:
 *
 *     public array $passwordValidators = [
 *         CompositionValidator::class,
 *         NothingPersonalValidator::class,
 *         DictionaryValidator::class,
 *         HistoryValidator::class,
 *     ];
 */
class HistoryValidator extends BaseValidator implements PasswordValidatorInterface
{
    public function check(string $password, ?User $user = null): Result
    {
        $retain = (int) ($this->config->passwordHistorySize ?? 0);

        if ($retain <= 0 || ! $user instanceof User || $user->id === null) {
            return new Result(['success' => true]);
        }

        /** @var PasswordHistoryModel $history */
        $history = model(PasswordHistoryModel::class);

        if ($history->matchesRecent($user, $password, $retain)) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.passwordHistoryReuse', [$retain]),
            ]);
        }

        return new Result(['success' => true]);
    }
}

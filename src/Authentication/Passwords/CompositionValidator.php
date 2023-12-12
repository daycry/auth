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
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Interfaces\PasswordValidatorInterface;
use Daycry\Auth\Result;

/**
 * Class CompositionValidator
 *
 * Checks the general makeup of the password.
 *
 * While older composition checks might have included different character
 * groups that you had to include, current NIST standards prefer to simply
 * set a minimum length and a long maximum (128+ chars).
 *
 * @see https://pages.nist.gov/800-63-3/sp800-63b.html#sec5
 */
class CompositionValidator extends BaseValidator implements PasswordValidatorInterface
{
    /**
     * Returns true when the password passes this test.
     * The password will be passed to any remaining validators.
     * False will immediately stop validation process
     */
    public function check(string $password, ?User $user = null): Result
    {
        if (empty($this->config->minimumPasswordLength)) {
            throw AuthenticationException::forUnsetPasswordLength();
        }

        $passed = mb_strlen($password, 'UTF-8') >= $this->config->minimumPasswordLength;

        if (! $passed) {
            return new Result([
                'success'   => false,
                'reason'    => lang('Auth.errorPasswordLength', [$this->config->minimumPasswordLength]),
                'extraInfo' => lang('Auth.suggestPasswordLength'),
            ]);
        }

        return new Result([
            'success' => true,
        ]);
    }
}

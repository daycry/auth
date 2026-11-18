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

namespace Daycry\Auth\Interfaces;

use CodeIgniter\Exceptions\RuntimeException;
use Daycry\Auth\Entities\User;

/**
 * Application contract for replacing TokenEmailSender without inheritance.
 */
interface TokenEmailSenderInterface
{
    /**
     * Generates a token identity and sends an email to the user.
     *
     * @param User                 $user           The user to send the email to
     * @param string               $identityType   Identity type constant (e.g. Session::ID_TYPE_MAGIC_LINK)
     * @param int                  $lifetime       Token lifetime in seconds
     * @param string               $emailSubject   Email subject line (lang key already resolved)
     * @param string               $emailView      View path for the email body
     * @param array<string, mixed> $extraViewData  Additional data passed to the email view
     * @param ?callable            $tokenGenerator Optional token factory (defaults to a 20-char crypto string)
     * @param string|null          $to             Recipient; defaults to the user's e-mail (e.g. a new address to confirm)
     * @param array<string, mixed> $identityExtra  Data stored (JSON) in the token identity's `extra` column
     *
     * @return string The generated raw token
     *
     * @throws RuntimeException When the email cannot be sent
     */
    public function sendTokenEmail(
        User $user,
        string $identityType,
        int $lifetime,
        string $emailSubject,
        string $emailView,
        array $extraViewData = [],
        ?callable $tokenGenerator = null,
        ?string $to = null,
        array $identityExtra = [],
    ): string;
}

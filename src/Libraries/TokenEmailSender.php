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

namespace Daycry\Auth\Libraries;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Traits\Viewable;

/**
 * Generates a cryptographic token, stores it as an identity, and sends
 * an email with the token to the user.
 *
 * Shared by MagicLinkController and PasswordResetController.
 */
class TokenEmailSender
{
    use Viewable;

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
    ): string {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous identities of this type
        $identityModel->deleteIdentitiesByType($user, $identityType);

        // Generate the token and save it as an identity. The RAW token is
        // emailed to the user, but only its non-reversible SHA-256 hash is
        // persisted — so a database/backup leak never yields a directly-usable
        // login/reset token. @see UserIdentityModel::getIdentityBySecret()
        //
        // The identities table has a UNIQUE(type, secret) key. Long crypto
        // tokens effectively never collide, but a short numeric code (custom
        // generator) can clash with another user's active code, so regenerate
        // and retry on a unique-constraint violation (mirrors createCodeIdentity).
        helper('text');
        $token    = '';
        $maxTries = 5;

        while (true) {
            $token = $tokenGenerator !== null
                ? (string) $tokenGenerator()
                : random_string('crypto', 20);

            try {
                $identityModel->insert([
                    'user_id' => $user->id,
                    'type'    => $identityType,
                    'secret'  => hash('sha256', $token),
                    'expires' => Time::now()->addSeconds($lifetime)->format('Y-m-d H:i:s'),
                ]);

                break;
            } catch (DatabaseException $e) {
                if (--$maxTries <= 0) {
                    throw $e;
                }
            }
        }

        // Gather common email context
        /** @var IncomingRequest $request */
        $request   = service('request');
        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Build view data. `$user` is provided so email templates can address
        // the recipient (the magic-link email renders $user->username).
        $viewData = array_merge([
            'user'      => $user,
            'token'     => $token,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'date'      => $date,
        ], $extraViewData);

        // Send the email
        helper('email');
        $email = emailer()->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($user->email);
        $email->setSubject($emailSubject);
        $email->setMessage($this->view($emailView, $viewData));

        if ($email->send(false) === false) {
            log_message('error', $email->printDebugger(['headers']));

            $email->clear();

            throw new RuntimeException(
                'Cannot send email for user: ' . $user->email,
            );
        }

        $email->clear();

        return $token;
    }
}

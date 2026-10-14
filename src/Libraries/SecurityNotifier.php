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

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Traits\Viewable;
use Throwable;

/**
 * First-party security notification e-mails ("your password was changed",
 * "your e-mail address is being changed", ...).
 *
 * Every send is gated on AuthSecurity::$securityNotificationEmails and is
 * swallow-and-log: a delivery failure is written to the log and never breaks
 * the account action that triggered it. Resolve it as
 * `service('securityNotifier')` and override that binding to customise
 * delivery (queue, another transport, ...).
 */
class SecurityNotifier
{
    use Viewable;

    /**
     * Fallback templates for apps whose published Config\Auth::$views
     * predates these keys.
     */
    private const DEFAULT_VIEWS = [
        'security-password-changed-email'       => '\Daycry\Auth\Views\Email\security_notice_email',
        'security-email-change-requested-email' => '\Daycry\Auth\Views\Email\security_notice_email',
        'security-email-changed-email'          => '\Daycry\Auth\Views\Email\security_notice_email',
        'security-account-deleted-email'        => '\Daycry\Auth\Views\Email\security_notice_email',
        'security-totp-disabled-email'          => '\Daycry\Auth\Views\Email\security_notice_email',
        'security-suspicious-login-email'       => '\Daycry\Auth\Views\Email\suspicious_login_alert',
    ];

    /**
     * The account password was changed (or set for the first time).
     */
    public function passwordChanged(User $user): bool
    {
        return $this->send($user, $user->email, 'PasswordChanged', 'security-password-changed-email');
    }

    /**
     * An e-mail change was requested: warns the CURRENT address at request
     * time, so a hijacked session cannot silently move the account away.
     */
    public function emailChangeRequested(User $user, string $newEmail): bool
    {
        return $this->send($user, $user->email, 'EmailChangeRequested', 'security-email-change-requested-email', [
            'newEmail' => $newEmail,
        ]);
    }

    /**
     * The e-mail change was confirmed: notifies the PREVIOUS address.
     */
    public function emailChanged(User $user, string $oldEmail): bool
    {
        return $this->send($user, $oldEmail, 'EmailChanged', 'security-email-changed-email', [
            'newEmail' => $user->email,
        ]);
    }

    /**
     * Farewell notice, sent right before the account is anonymised.
     */
    public function accountDeleted(User $user): bool
    {
        return $this->send($user, $user->email, 'AccountDeleted', 'security-account-deleted-email');
    }

    /**
     * Two-factor authentication was turned off.
     */
    public function totpDisabled(User $user): bool
    {
        return $this->send($user, $user->email, 'TotpDisabled', 'security-totp-disabled-email');
    }

    /**
     * A successful sign-in looked unusual. Also requires
     * AuthSecurity::$suspiciousLoginAlerts (the detector only runs then).
     *
     * @param list<string> $flags
     */
    public function suspiciousLogin(User $user, array $flags, string $ipAddress, string $userAgent): bool
    {
        if (! (bool) setting('AuthSecurity.suspiciousLoginAlerts')) {
            return false;
        }

        return $this->send($user, $user->email, 'SuspiciousLogin', 'security-suspicious-login-email', [
            'flags'     => $flags,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ]);
    }

    /**
     * Renders and sends one notification.
     *
     * The subject is `Auth.security{$notice}Subject`; the default template
     * reads the matching Heading/Body lines.
     *
     * @param string               $notice  Notice name, e.g. 'PasswordChanged'
     * @param string               $viewKey Key in Auth::$views
     * @param array<string, mixed> $data
     *
     * @return bool Whether the e-mail was handed to the transport
     */
    protected function send(User $user, ?string $to, string $notice, string $viewKey, array $data = []): bool
    {
        if (! (bool) setting('AuthSecurity.securityNotificationEmails') || $to === null || $to === '') {
            return false;
        }

        try {
            /** @var IncomingRequest $request */
            $request = service('request');

            $data = array_merge([
                'notice'    => $notice,
                'user'      => $user,
                'ipAddress' => $request->getIPAddress(),
                'userAgent' => (string) $request->getUserAgent(),
                'date'      => Time::now()->toDateTimeString(),
            ], $data);

            helper('email');
            $email = emailer()->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
            $email->setTo($to);
            $email->setSubject(lang('Auth.security' . $notice . 'Subject'));
            $view = setting('Auth.views')[$viewKey] ?? self::DEFAULT_VIEWS[$viewKey];
            $email->setMessage($this->view($view, $data, ['debug' => false]));

            $sent = $email->send(false);

            if ($sent === false) {
                log_message('error', 'Security notification "{view}" could not be sent: {debug}', [
                    'view'  => $viewKey,
                    'debug' => $email->printDebugger(['headers']),
                ]);
            }

            $email->clear();

            return $sent !== false;
        } catch (Throwable $e) {
            log_message('error', 'Security notification "{view}" failed: {message}', [
                'view'    => $viewKey,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

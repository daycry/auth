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

use Daycry\Auth\Interfaces\EmailDeliveryInterface;

/**
 * Default delivery through the application's existing CodeIgniter mail config.
 */
class CodeIgniterEmailDelivery implements EmailDeliveryInterface
{
    /**
     * Sends immediately using the configured SMTP, sendmail or mail transport.
     */
    public function send(string $to, string $subject, string $html): bool
    {
        $preferences = [];

        foreach (['userAgent', 'protocol', 'mailPath', 'SMTPHost', 'SMTPUser', 'SMTPPass', 'SMTPPort', 'SMTPTimeout', 'SMTPKeepAlive', 'SMTPCrypto', 'wordWrap', 'wrapChars', 'mailType', 'charset', 'validate', 'priority', 'CRLF', 'newline', 'BCCBatchMode', 'BCCBatchSize', 'DSN'] as $name) {
            $preferences[$name] = setting('Email.' . $name);
        }
        $preferences['mailType'] = 'html';
        $email                   = service('email')->initialize($preferences)
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');

        try {
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($html);
            $sent = $email->send(false);

            if ($sent === false) {
                // Do not log message bodies, confirmation URLs or token-bearing headers.
                log_message('error', 'Authentication email delivery failed.');
            }

            return $sent !== false;
        } finally {
            $email->clear();
        }
    }
}

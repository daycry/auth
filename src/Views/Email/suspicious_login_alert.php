<?php

declare(strict_types=1);

/**
 * @var \Daycry\Auth\Entities\User $user
 * @var list<string>               $flags
 * @var string                     $ipAddress
 * @var string                     $userAgent
 * @var string                     $date
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= esc(lang('Auth.suspiciousLoginSubject')) ?: 'Suspicious sign-in detected' ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <h2><?= esc(lang('Auth.suspiciousLoginHeading')) ?: 'We noticed an unusual sign-in to your account' ?></h2>

    <p>
        <?= esc(sprintf((string) (lang('Auth.suspiciousLoginGreeting') ?: 'Hi %s,'), $user->username ?? $user->email ?? '')) ?>
    </p>

    <p>
        <?= esc(lang('Auth.suspiciousLoginBody')) ?: 'A successful sign-in to your account looked different from your usual activity. If this was you, no action is needed. If you do not recognise it, secure your account immediately.' ?>
    </p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin: 12px 0;">
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginWhen')) ?: 'When' ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($date) ?></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginIp')) ?: 'IP address' ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($ipAddress) ?></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginAgent')) ?: 'Device / Browser' ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($userAgent) ?></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginFlags')) ?: 'Reasons' ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc(implode(', ', $flags)) ?></td>
        </tr>
    </table>

    <p>
        <?= esc(lang('Auth.suspiciousLoginAdvice')) ?: 'If this was not you: change your password immediately, review the active sessions on your account, and enable two-factor authentication.' ?>
    </p>

    <p style="font-size: 12px; color: #888;">
        <?= esc(lang('Auth.suspiciousLoginFooter')) ?: 'You are receiving this email because suspicious-login alerts are enabled for your account.' ?>
    </p>
</body>
</html>

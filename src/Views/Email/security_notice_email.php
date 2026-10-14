<?php
/**
 * Generic security-notification e-mail, rendered by SecurityNotifier.
 *
 * @var string                   $notice    Notice name, e.g. 'PasswordChanged' (selects Auth.security{Notice}* lines)
 * @var Daycry\Auth\Entities\User $user
 * @var string                   $date
 * @var string                   $ipAddress
 * @var string                   $userAgent
 * @var string|null              $newEmail  Present for e-mail change notices
 */
$newEmail ??= '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(lang('Auth.security' . $notice . 'Subject')) ?></title>
</head>
<body style="font-family: Helvetica, Arial, sans-serif; color: #222; line-height: 1.5;">
    <h2><?= esc(lang('Auth.security' . $notice . 'Heading')) ?></h2>

    <p><?= esc(lang('Auth.securityNoticeGreeting', [(string) ($user->username ?? $user->email ?? '')])) ?></p>

    <p><?= esc(lang('Auth.security' . $notice . 'Body', [$newEmail])) ?></p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin: 12px 0;">
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginWhen')) ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($date) ?></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginIp')) ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($ipAddress) ?></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ddd; background: #f7f7f7;"><strong><?= esc(lang('Auth.suspiciousLoginAgent')) ?></strong></td>
            <td style="border: 1px solid #ddd;"><?= esc($userAgent) ?></td>
        </tr>
    </table>

    <p><?= esc(lang('Auth.securityNoticeAdvice')) ?></p>

    <p style="font-size: 12px; color: #888;"><?= esc(lang('Auth.securityNoticeFooter')) ?></p>
</body>
</html>

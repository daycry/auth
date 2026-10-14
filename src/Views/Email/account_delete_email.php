<?php
/**
 * Account-deletion confirmation link for password-less accounts.
 *
 * @var string                    $token Raw single-use token
 * @var Daycry\Auth\Entities\User $user
 */
$link ??= url_to('security-confirm-delete') . '?token=' . urlencode((string) $token);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(lang('Auth.deleteAccountSubject')) ?></title>
</head>
<body style="font-family: Helvetica, Arial, sans-serif; color: #222; line-height: 1.5;">
    <p><?= esc(lang('Auth.securityNoticeGreeting', [(string) ($user->username ?? $user->email ?? '')])) ?></p>

    <p><?= esc(lang('Auth.deleteAccountMailBody')) ?></p>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important;">
        <tbody>
            <tr>
                <td style="line-height: 24px; font-size: 16px; border-radius: 6px; margin: 0;" align="center" bgcolor="#dc3545">
                    <a href="<?= esc($link) ?>" style="color: #ffffff; font-size: 16px; text-decoration: none; border-radius: 6px; line-height: 20px; display: inline-block; white-space: nowrap; background-color: #dc3545; padding: 8px 12px; border: 1px solid #dc3545;"><?= esc(lang('Auth.deleteAccountButton')) ?></a>
                </td>
            </tr>
        </tbody>
    </table>

    <p style="font-size: 12px; color: #888;"><?= esc(lang('Auth.securityNoticeFooter')) ?></p>
</body>
</html>

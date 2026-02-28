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

return [
    // Exceptions
    'unknownAuthenticator'  => '{0} ist kein gültiger Authentifikator.',
    'unknownUserProvider'   => 'Der zu verwendende User Provider konnte nicht ermittelt werden.',
    'invalidUser'           => 'Der angegebene Benutzer kann nicht gefunden werden.',
    'bannedUser'            => 'Anmelden nicht möglich da Ihr Benutzer derzeit gesperrt ist.',
    'logOutBannedUser'      => 'Ihr Benutzer wurde abgemeldet und gesperrt.',
    'badAttempt'            => 'Sie konnten nicht angemeldet werden. Bitte überprüfen Sie Ihre Anmeldedaten.',
    'noPassword'            => 'Kann einen Benutzer ohne Passwort nicht validieren.',
    'invalidPassword'       => 'Sie können nicht angemeldet werden. Bitte überprüfen Sie Ihr Passwort.',
    'noToken'               => 'Jede Anfrage muss ein Überbringer-Token im {0}-Header enthalten.',
    'badToken'              => 'Das Zugriffstoken ist ungültig.',
    'oldToken'              => 'Das Zugriffstoken ist abgelaufen.',
    'noUserEntity'          => 'Die Benutzerentität muss für die Passwortüberprüfung angegeben werden.',
    'invalidEmail'          => 'Es konnte nicht überprüft werden, ob die E-Mail-Adresse mit der gespeicherten übereinstimmt.',
    'unableSendEmailToUser' => 'Leider gab es ein Problem beim Senden der E-Mail. Wir konnten keine E-Mail an "{0}" senden.',
    'throttled'             => 'Es wurden zu viele Anfragen von dieser IP-Adresse gestellt. Sie können es in {0} Sekunden erneut versuchen.',
    'notEnoughPrivilege'    => 'Sie haben nicht die erforderliche Berechtigung, um den gewünschten Vorgang auszuführen.',
    // JWT Exceptions
    'invalidJWT'     => '(To be translated) The token is invalid.',
    'expiredJWT'     => '(To be translated) The token has expired.',
    'beforeValidJWT' => '(To be translated) The token is not yet available.',

    'email'           => 'E-Mail-Adresse',
    'username'        => 'Benutzername',
    'password'        => 'Passwort',
    'passwordConfirm' => 'Passwort (erneut)',
    'haveAccount'     => 'Haben Sie bereits ein Konto?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Bestätigen',
    'send'    => 'Senden',

    // Registration
    'register'         => 'Registrieren',
    'registerDisabled' => 'Die Registrierung ist derzeit nicht erlaubt.',
    'registerSuccess'  => 'Willkommen an Bord!',

    // Login
    'login'              => 'Anmelden',
    'needAccount'        => 'Brauchen Sie ein Konto?',
    'rememberMe'         => 'Angemeldet bleiben',
    'forgotPassword'     => 'Passwort vergessen?',
    'useMagicLink'       => 'Einen Login-Link verwenden',
    'magicLinkSubject'   => 'Ihr Login-Link',
    'magicTokenNotFound' => 'Der Link konnte nicht verifiziert werden.',
    'magicLinkExpired'   => 'Sorry, der Link ist abgelaufen.',
    'checkYourEmail'     => 'Prüfen Sie Ihre E-Mail!',
    'magicLinkDetails'   => 'Wir haben Ihnen gerade eine E-Mail mit einem Login-Link geschickt. Er ist nur für {0} Minuten gültig.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Sie haben sich erfolgreich abgemeldet.',
    'backToLogin'        => 'Zurück zur Anmeldung',

    // Passwords
    'errorPasswordLength'       => 'Passwörter müssen mindestens {0, number} Zeichen lang sein.',
    'suggestPasswordLength'     => 'Passphrasen - bis zu 255 Zeichen lang - ergeben sicherere Passwörter, die leicht zu merken sind.',
    'errorPasswordCommon'       => 'Das Passwort darf kein allgemeines Passwort sein.',
    'suggestPasswordCommon'     => 'Das Passwort wurde mit über 65-tausend häufig verwendeten Passwörtern oder Passwörtern, die durch Hacks bekannt geworden sind, abgeglichen.',
    'errorPasswordPersonal'     => 'Passwörter dürfen keine gehashten persönlichen Informationen enthalten.',
    'suggestPasswordPersonal'   => 'Variationen Ihrer E-Mail-Adresse oder Ihres Benutzernamens sollten nicht für Passwörter verwendet werden.',
    'errorPasswordTooSimilar'   => 'Das Passwort ist dem Benutzernamen zu ähnlich.',
    'suggestPasswordTooSimilar' => 'Verwenden Sie keine Teile Ihres Benutzernamens in Ihrem Passwort.',
    'errorPasswordPwned'        => 'Das Passwort {0} wurde aufgrund einer Datenschutzverletzung aufgedeckt und wurde {1, number} Mal in {2} kompromittierten Passwörtern gesehen.',
    'suggestPasswordPwned'      => '{0} sollte niemals als Passwort verwendet werden. Wenn Sie es irgendwo verwenden, ändern Sie es sofort.',
    'errorPasswordEmpty'        => 'Ein Passwort ist erforderlich.',
    'errorPasswordTooLongBytes' => 'Das Passwort darf die Länge von {param} Bytes nicht überschreiten.',
    'passwordChangeSuccess'     => 'Passwort erfolgreich geändert',
    'userDoesNotExist'          => 'Passwort wurde nicht geändert. Der Benutzer existiert nicht',
    'resetTokenExpired'         => 'Tut mir leid. Ihr Reset-Token ist abgelaufen.',

    // Email Globals
    'emailInfo'      => 'Einige Informationen über die Person:',
    'emailIpAddress' => 'IP Adresse:',
    'emailDevice'    => 'Gerät:',
    'emailDate'      => 'Datum:',

    // 2FA
    'email2FATitle'       => 'Zwei-Faktor-Authentifizierung',
    'confirmEmailAddress' => 'Bestätigen Sie Ihre E-Mail-Adresse.',
    'emailEnterCode'      => 'Bestätigen Sie Ihre E-Mail',
    'emailConfirmCode'    => 'Geben Sie den 6-stelligen Code ein, den wir gerade an Ihre E-Mail-Adresse geschickt haben.',
    'email2FASubject'     => 'Ihr Authentifizierungscode',
    'email2FAMailBody'    => 'Ihr Authentifizierungscode lautet:',
    'invalid2FAToken'     => 'Der Code war falsch.',
    'need2FA'             => 'Sie müssen eine Zwei-Faktor-Verifizierung durchführen.',
    // TOTP 2FA
    'totpTitle'         => 'Two-Factor Authentication',
    'totpEnterCode'     => 'Enter the 6-digit code from your authenticator app.',
    'invalidTotpToken'  => 'The code was incorrect or has expired. Please try again.',
    'needTotp'          => 'Enter the code from your authenticator app.',
    'totpNotConfigured' => 'TOTP two-factor authentication is not configured for this user.',
    // TOTP 2FA — setup
    'totpSetupTitle'         => 'Set Up Two-Factor Authentication',
    'totpSetupIntro'         => 'Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.).',
    'totpQrAlt'              => 'QR code for authenticator app',
    'totpManualKey'          => 'Or enter this key manually in your app:',
    'totpSetupConfirmIntro'  => 'Once scanned, enter the 6-digit code shown in your app to confirm setup.',
    'totpSetupActivate'      => 'Activate',
    'totpSetupSuccess'       => 'Two-Factor Authentication Enabled',
    'totpSetupSuccessDetail' => 'Your account is now protected with TOTP two-factor authentication.',
    'totpSetupContinue'      => 'Continue',
    'totpSetupInvalidCode'   => 'The code was incorrect. Please try scanning the QR code again.',
    'needVerification'       => 'Überprüfen Sie Ihre E-Mail, um die Kontoaktivierung abzuschließen.',

    // Activate
    'emailActivateTitle'    => 'E-Mail-Aktivierung',
    'emailActivateBody'     => 'Wir haben Ihnen gerade eine E-Mail mit einem Code zur Bestätigung Ihrer E-Mail-Adresse geschickt. Kopieren Sie diesen Code und fügen Sie ihn unten ein.',
    'emailActivateSubject'  => 'Ihr Aktivierungscode',
    'emailActivateMailBody' => 'Bitte verwenden Sie den unten stehenden Code, um Ihr Konto zu aktivieren und die Website zu nutzen.',
    'invalidActivateToken'  => 'Der Code war falsch.',
    'needActivate'          => 'Sie müssen Ihre Anmeldung abschließen, indem Sie den an Ihre E-Mail-Adresse gesendeten Code bestätigen.',
    'activationBlocked'     => 'Bevor Sie sich anmelden können muss das Konto aktiviert werden.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} ist eine ungültige Gruppe.',
    'missingTitle' => 'Gruppen müssen einen Titel haben.',

    // Permissions
    'unknownPermission' => '{0} ist keine gültige Berechtigung.',

    // Password Reset (EN fallback - translation pending)
    'passwordResetTitle'        => 'Reset Your Password',
    'passwordResetIntro'        => 'Enter your email address and we\'ll send you a link to reset your password.',
    'passwordResetSent'         => 'If that email address is in our database, you will receive a password reset link shortly.',
    'passwordResetSubject'      => 'Reset Your Password',
    'passwordResetEmailBody'    => 'Click the link below to reset your password. The link is valid for {0} minutes.',
    'passwordResetButton'       => 'Reset Password',
    'passwordResetFormTitle'    => 'Set New Password',
    'passwordResetFormIntro'    => 'Enter your new password below.',
    'passwordResetSuccess'      => 'Your password has been reset. You can now log in with your new password.',
    'passwordResetTokenInvalid' => 'The password reset link is invalid. Please request a new one.',
    'passwordResetTokenExpired' => 'The password reset link has expired. Please request a new one.',
    'passwordResetNewPassword'  => 'New Password',
    'passwordResetConfirm'      => 'Confirm New Password',
    'passwordResetSubmit'       => 'Set New Password',
    // Force Password Reset (EN fallback)
    'forceResetTitle'        => 'Password Reset Required',
    'forceResetIntro'        => 'For security reasons, you must change your password before continuing.',
    'forceResetSuccess'      => 'Your password has been updated successfully.',
    'forceResetCurrentLabel' => 'Current Password',
    'forceResetNewLabel'     => 'New Password',
    'forceResetConfirmLabel' => 'Confirm New Password',
    'forceResetSubmit'       => 'Update Password',
    'invalidCurrentPassword' => 'The current password you entered is incorrect.',
    // Per-user lockout (EN fallback)
    'userLockedOut' => 'Your account has been temporarily locked due to too many failed login attempts. Please try again in {0} minutes.',
    'userUnlocked'  => 'The account has been unlocked.',
    // Self-service password change (EN fallback)
    'changePasswordTitle'   => 'Change Password',
    'changePasswordSuccess' => 'Your password has been changed successfully.',
    'changePasswordCurrent' => 'Current Password',
    'changePasswordNew'     => 'New Password',
    'changePasswordConfirm' => 'Confirm New Password',
    'changePasswordSubmit'  => 'Update Password',
    // Email change (EN fallback)
    'changeEmailTitle'        => 'Change Email Address',
    'changeEmailIntro'        => 'Enter your new email address below. A confirmation link will be sent to the new address.',
    'changeEmailSent'         => 'A confirmation link has been sent to your new email address. Please check your inbox.',
    'changeEmailSubject'      => 'Confirm Your New Email Address',
    'changeEmailMailBody'     => 'Click the link below to confirm your new email address.',
    'changeEmailButton'       => 'Confirm Email Change',
    'changeEmailSuccess'      => 'Your email address has been updated successfully.',
    'changeEmailTokenInvalid' => 'The confirmation link is invalid or has already been used.',
    'changeEmailTokenExpired' => 'The confirmation link has expired. Please request a new one.',
    'changeEmailLabel'        => 'New Email Address',
    'changeEmailSubmit'       => 'Send Confirmation',
    'changeEmailAlreadyUsed'  => 'That email address is already in use.',
    // OAuth unlinking (EN fallback)
    'unlinkOauthSuccess'    => 'The {0} account has been disconnected.',
    'unlinkOauthNotFound'   => 'No {0} account was found linked to your profile.',
    'unlinkOauthLastMethod' => 'You cannot remove your only authentication method. Please add a password or link another account first.',
    // New device notification (EN fallback)
    'newDeviceSubject'  => 'New sign-in to your account',
    'newDeviceMailBody' => 'A new sign-in was detected on your account from a new device or location.',
    'newDeviceIp'       => 'IP Address',
    'newDeviceDevice'   => 'Device',
    'newDeviceTime'     => 'Time',
    'newDeviceNotYou'   => 'If this wasn\'t you, please change your password immediately.',
    // JWT refresh (EN fallback)
    'invalidRefreshToken' => 'The refresh token is invalid or has expired.',
    'refreshTokenRevoked' => 'The refresh token has been revoked.',
    'revokedToken'        => 'The token has been revoked.',
];

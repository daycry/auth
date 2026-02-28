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
    'unknownAuthenticator'  => '{0} nije validan autentikator.',
    'unknownUserProvider'   => 'Nemoguće je odlučiti koji User Provider koristiti.',
    'invalidUser'           => 'Nemoguće locirati specifičnog korisnika.',
    'bannedUser'            => 'Nije moguće pristupanje sistemu.Vaš nalog je banovan.',
    'logOutBannedUser'      => 'Izlogovani ste jer je vaš nalog je banovan.',
    'badAttempt'            => 'Neuspešan pristup. Proverite kredencijale.',
    'noPassword'            => 'Neuspešna validacija korisnika bez lozinke.',
    'invalidPassword'       => 'Neuspešan pristup, Proverite vašu lozinku.',
    'noToken'               => 'Svaki zahtev mora imati bearer token u {0} zaglavlju.',
    'badToken'              => 'Pristupni token nije validan.',
    'oldToken'              => 'Pristupni token je istekao.',
    'noUserEntity'          => 'Korisnički entitet mora postojati za verifikaciju naloga.',
    'invalidEmail'          => 'Nije moguće potvrditi email adresu ne postoje pogodci u bazi podataka.',
    'unableSendEmailToUser' => 'Žao nam je ali slanje email poruke nije moguće. Nismo u mogućnosti poslati poruku na "{0}".',
    'throttled'             => 'Preveliki broj zahteva sa vaše IP adrese. Možete pokušati ponovo za {0} secondi.',
    'notEnoughPrivilege'    => 'Nemate dovoljan nivo autorizacije za zahtevanu akciju.',
    // JWT Exceptions
    'invalidJWT'     => '(To be translated) The token is invalid.',
    'expiredJWT'     => '(To be translated) The token has expired.',
    'beforeValidJWT' => '(To be translated) The token is not yet available.',

    'email'           => 'E-mail Adresa',
    'username'        => 'Korisničko ime',
    'password'        => 'Lozinka',
    'passwordConfirm' => 'Lozinka (ponovo)',
    'haveAccount'     => 'Već imate nalog?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Potvrdi',
    'send'    => 'Pošalji',

    // Registration
    'register'         => 'Registracija',
    'registerDisabled' => 'Registracija trenutno nije dozvoljena.',
    'registerSuccess'  => 'Dobrodošli!',

    // Login
    'login'              => 'Pristup',
    'needAccount'        => 'Potreban Vam je nalog?',
    'rememberMe'         => 'Zapmti me?',
    'forgotPassword'     => 'Zaboravljena lozinka?',
    'useMagicLink'       => 'Koristi pristupni link',
    'magicLinkSubject'   => 'Vaš pristupni link',
    'magicTokenNotFound' => 'Nije moguća verifikacija linka.',
    'magicLinkExpired'   => 'Žao nam je, link je istekao.',
    'checkYourEmail'     => 'Proverite Vaš email!',
    'magicLinkDetails'   => 'Upravo smo Vam poslali pristupni link. Pristupni link će biti validan još samo {0} minuta.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Uspešno ste se odjavili sa sistema.',
    'backToLogin'        => 'Nazad na prijavljivanje',

    // Passwords
    'errorPasswordLength'       => 'Lozinka mora biti najmanje {0, number} znakova dužine.',
    'suggestPasswordLength'     => 'Fraza lozinke - čak do 255 znakova dužine - napravite sigurniju lozinku koja se lako pamti.',
    'errorPasswordCommon'       => 'Lozinka ne može biti na listi čestih lozinki.',
    'suggestPasswordCommon'     => 'Lozinka je upoređena sa 65k čestih lozinki ili lozinki procurelih hakovanjem.',
    'errorPasswordPersonal'     => 'Lozinka ne može sadržati hešovane lične podatke.',
    'suggestPasswordPersonal'   => 'Varijacije bazirane na email adresi ne treba koristiti kao lozinku.',
    'errorPasswordTooSimilar'   => 'Lozinka je previše slična korisničkom imenu.',
    'suggestPasswordTooSimilar' => 'Ne koristite delove korisničkog imena za lozinku.',
    'errorPasswordPwned'        => 'Lozinka {0} je otkrivena prilikom napada {1, number} puta u {2} kompromitovanih lozinki.',
    'suggestPasswordPwned'      => '{0} nikada ne treba biti korišćen za lozinku. Ako to koristite bilo gde promenite lozinku odmah.',
    'errorPasswordEmpty'        => 'Lozinka je obavezna.',
    'errorPasswordTooLongBytes' => 'Lozinka ne može preći {param} bytova dužine.',
    'passwordChangeSuccess'     => 'Lozinka je uspešno promenjena',
    'userDoesNotExist'          => 'Lozinka nije promenjena. Korisnični nalog ne postoji',
    'resetTokenExpired'         => 'Žao nam je ali token je istekao.',

    // Email Globals
    'emailInfo'      => 'Neke informacije o osobi:',
    'emailIpAddress' => 'IP Adresa:',
    'emailDevice'    => 'Uređaj:',
    'emailDate'      => 'Datum:',

    // 2FA
    'email2FATitle'       => 'Dvofazna autentifikacija',
    'confirmEmailAddress' => 'Potvrdite Vašu email adresu.',
    'emailEnterCode'      => 'Unesite kod',
    'emailConfirmCode'    => 'Unesite 6-cifreni kod koji smo Vam poslali na email.',
    'email2FASubject'     => 'Vaš kod za autentifikaciju',
    'email2FAMailBody'    => 'Autentifikacioni kod:',
    'invalid2FAToken'     => 'Kod nije ispravan.',
    'need2FA'             => 'Morate dovršiti dvofaznu autentifikaciju.',
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
    'needVerification'       => 'Proverite email kako bi ste završili verifikaciju.',

    // Activate
    'emailActivateTitle'    => 'Aktivacija email-a',
    'emailActivateBody'     => 'Upravo smo Vam poslali kod za proveru email adrese. Molimo vas alepite kod ispod',
    'emailActivateSubject'  => 'Baš aktivacioni kod',
    'emailActivateMailBody' => 'Koristite kod u nastavku kako bi ste aktivirali Vaš nalog i počeli korišćenje servisa.',
    'invalidActivateToken'  => 'Kod nije ispravan.',
    'needActivate'          => 'Morate dovršiti registraciju potvrdom koda poslatog na vašu email adresu.',
    'activationBlocked'     => 'Morate aktivirati vaš nalog pre pristupanja sistemu.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} neispravna grupa.',
    'missingTitle' => 'Grupa mora imati naziv.',

    // Permissions
    'unknownPermission' => '{0} nije validno odobrenje.',

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

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
    'unknownAuthenticator'  => '{0} non è un autenticatore valido.',
    'unknownUserProvider'   => 'Impossibile determinare lo User Provider da usare.',
    'invalidUser'           => 'Impossibile trovere l\'utente specificato.',
    'bannedUser'            => '(To be translated) Can not log you in as you are currently banned.',
    'logOutBannedUser'      => '(To be translated) You have been logged out because you have been banned.',
    'badAttempt'            => 'Impossibile accedere. Si prega di verificare le proprie credenziali.',
    'noPassword'            => 'Impossibile validare un utente senza una password.',
    'invalidPassword'       => 'Impossibile accedere. Si prega di verificare la propria password.',
    'noToken'               => 'Ogni richiesta deve avere un token bearer nell\' header {0}.',
    'badToken'              => 'Il token di accesso non è valido.',
    'oldToken'              => 'Il token di accesso è scaduto.',
    'noUserEntity'          => 'Deve essere fornita una User Entity per la validazione della password.',
    'invalidEmail'          => 'Impossibile verificare che l\'indirizzo email corrisponda all\'email nel record.',
    'unableSendEmailToUser' => 'Spiacente, c\'è stato un problema inviando l\'email. Non possiamo inviare un\'email a "{0}".',
    'throttled'             => 'Troppe richieste effettuate da questo indirizzo IP. Potrai riprovare tra {0} secondi.',
    'notEnoughPrivilege'    => 'Non si dispone dell\'autorizzazione necessaria per eseguire l\'operazione desiderata.',
    // JWT Exceptions
    'invalidJWT'     => '(To be translated) The token is invalid.',
    'expiredJWT'     => '(To be translated) The token has expired.',
    'beforeValidJWT' => '(To be translated) The token is not yet available.',

    'email'           => 'Indirizzo Email',
    'username'        => 'Nome Utente',
    'password'        => 'Password',
    'passwordConfirm' => 'Password (ancora)',
    'haveAccount'     => 'Hai già un account?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Conferma',
    'send'    => 'Invia',

    // Registration
    'register'         => 'Registrazione',
    'registerDisabled' => 'La registrazione non è al momento consentita.',
    'registerSuccess'  => 'Benvenuto a bordo!',

    // Login
    'login'              => 'Login',
    'needAccount'        => 'Hai bisogno di un account?',
    'rememberMe'         => 'Ricordami?',
    'forgotPassword'     => 'Password dimenticata?',
    'useMagicLink'       => 'Usa un Login Link',
    'magicLinkSubject'   => 'Il tuo Login Link',
    'magicTokenNotFound' => 'Impossibile verificare il link.',
    'magicLinkExpired'   => 'Spiacente, il link è scaduto.',
    'checkYourEmail'     => 'Controlla la tua email!',
    'magicLinkDetails'   => 'Ti abbiamo appena inviato una mail contenente un Login link. È valido solo per {0} minuti.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Hai effettuato il logout con successo.',
    'backToLogin'        => 'Torna al login',

    // Passwords
    'errorPasswordLength'       => 'Le password devono essere lunghe almeno {0, number} ccaratteri.',
    'suggestPasswordLength'     => 'Le Pass phrases - lunghe fino a 255 caratteri - fanno password più sicure e più facili da ricordare.',
    'errorPasswordCommon'       => 'La password non deve essere una passowrd comune.',
    'suggestPasswordCommon'     => 'La password è stata controllata in una lista di oltre 65k password comunemente usate o password che sono state trafugate attraverso hacks.',
    'errorPasswordPersonal'     => 'Le password non possono contenere informazioni personali.',
    'suggestPasswordPersonal'   => 'Varianti del tuo indirizzo email o username non dovrebbero essere usate come password.',
    'errorPasswordTooSimilar'   => 'La password è troppo simile all\'username.',
    'suggestPasswordTooSimilar' => 'Non utilizzare parti del tuo username nella password.',
    'errorPasswordPwned'        => 'La password {0} è stata esposta ad un furto di dati ed è stata vista {1, number} volte in {2} di password compromesse.',
    'suggestPasswordPwned'      => '{0} non dovrebbe mai essere usata come password. Se la stai utilizzando da qualche parte, cambiala immediatamente.',
    'errorPasswordEmpty'        => 'Una password è richiesta.',
    'errorPasswordTooLongBytes' => '(To be translated) Password cannot exceed {param} bytes in length.',
    'passwordChangeSuccess'     => 'La password è stata cambiata con successo',
    'userDoesNotExist'          => 'La password non è stata cambiata. L\'utente non esiste',
    'resetTokenExpired'         => 'Spiacente. Il tuo reset token è scaduto.',

    // Email Globals
    'emailInfo'      => 'Alcune informazioni sulla persona:',
    'emailIpAddress' => 'Indirizo IP:',
    'emailDevice'    => 'Dispositivo:',
    'emailDate'      => 'Data:',

    // 2FA
    'email2FATitle'       => 'Autenticazione a due fattori',
    'confirmEmailAddress' => 'Conferma il tuo indirizzo email.',
    'emailEnterCode'      => 'Conferma la tua Email',
    'emailConfirmCode'    => 'Inserisci il codice a 6 cifre che abbiamo mandato al tuo indirizzo email.',
    'email2FASubject'     => 'Il tuo codice di autenticazione',
    'email2FAMailBody'    => 'Il tuo codice di autenticazione è:',
    'invalid2FAToken'     => 'Il codice era sbagliato.',
    'need2FA'             => 'Devi completare l\'autenticazione a due fattori.',
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
    'needVerification'       => 'Controlla la tua email per completare l\'attivazione dell\'account.',

    // Activate
    'emailActivateTitle'    => 'Attivazione tramite Email',
    'emailActivateBody'     => 'Ti abbiamo mandato una email con un codice per confermare il tuo indirizzo email. Copia quel codice e incollalo qui sotto.',
    'emailActivateSubject'  => 'Il tuo codice di attivazione',
    'emailActivateMailBody' => 'Perfavore usa il codice qui sotto per attivare il tuo acccount ed iniziare ad usare il sito.',
    'invalidActivateToken'  => 'Il codice era sbagliato.',
    'needActivate'          => 'Devi completare la registrazione confermando il codice inviato al tuo indrizzo email.',
    'activationBlocked'     => '(to be translated) You must activate your account before logging in.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} non è un gruppo valido.',
    'missingTitle' => 'I gruppi devono avere un titolo.',

    // Permissions
    'unknownPermission' => '{0} non è un permesso valido.',

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

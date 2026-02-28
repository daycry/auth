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
    'unknownAuthenticator'  => '{0} nėra teisingas autentifikatorius.',
    'unknownUserProvider'   => 'Nepavyksta nustatyti, kokį reikėtų naudoti vartotojų šaltinį.',
    'invalidUser'           => 'Nepavyksta rasti nurodyto vartotojo.',
    'bannedUser'            => 'Jūsų vartotojas uždraustas, todėl prisijungti nepavyks.',
    'logOutBannedUser'      => 'Sistema jus išregistravo, nes Jūsų vartotojas uždraustas.',
    'badAttempt'            => 'Nepavyksta Jūsų prijungti. Patikrinkite prisijungimo duomenis.',
    'noPassword'            => 'Negalima patvirtinti vartotojo be slaptažodžio.',
    'invalidPassword'       => 'Nepavyksta Jūsų prijungti. Patikrinkite slaptažodį.',
    'noToken'               => 'Kiekviena užklausa turi turėti prieigos raštą antraštėje {0}.',
    'badToken'              => 'Prieigos raktas neteisingas.',
    'oldToken'              => 'Prieigos raktas nebegalioja.',
    'noUserEntity'          => 'Slaptažodžio patikrinimui turi būti pateiktas vartotojo subjektas.',
    'invalidEmail'          => 'Neišeina patvirtinti, kad pateiktas el. pašto adresas atitinka turimą el. pašto įrašą.',
    'unableSendEmailToUser' => 'Deja, nepavyko išsiųsti el. laiško. Nepavyko išsiųsti laiško adresu "{0}".',
    'throttled'             => 'Per daug užklausų iš šio IP adreso. Galite pamėginti iš naujo po {0} sekundžių.',
    'notEnoughPrivilege'    => 'Neturite operacijai atlikti užtektinų leidimų.',
    // JWT Exceptions
    'invalidJWT'     => 'Raktas neteisingai suformuotas.',
    'expiredJWT'     => 'Rakto galiojimas pasibaigęs.',
    'beforeValidJWT' => 'Rakto kol kas dar nėra.',

    'email'           => 'El. pašto adresas',
    'username'        => 'Vartotojo vardas',
    'password'        => 'Slaptažodis',
    'passwordConfirm' => 'Slaptažodis (pakartoti)',
    'haveAccount'     => 'Jau turite paskyrą?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Patvirtinti',
    'send'    => 'Siųsti',

    // Registration
    'register'         => 'Registruotis',
    'registerDisabled' => 'Šiuo metu registracija neleidžiama.',
    'registerSuccess'  => 'Sveiki prisijungę!',

    // Login
    'login'              => 'Prisijungimas',
    'needAccount'        => 'Reikia paskyros?',
    'rememberMe'         => 'Atsiminti mane?',
    'forgotPassword'     => 'Pamiršote slaptažodį?',
    'useMagicLink'       => 'Naudoti prisijungimo nuorodą',
    'magicLinkSubject'   => 'Jūsų prisijungimo nuoroda',
    'magicTokenNotFound' => 'Nepavyksta patvirtinti nuorodos.',
    'magicLinkExpired'   => 'Deja, nuorodos galiojimas baigėsi.',
    'checkYourEmail'     => 'Patikrinkite savo el. paštą!',
    'magicLinkDetails'   => 'Mes ką tik išsiuntėme Jums el. laišką su prisijungimo nuoroda. Ji galios tiki {0} minučių(-es).',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Jūs sėkmingai atsijungėte.',
    'backToLogin'        => 'Grįžti į prisijungimą',

    // Passwords
    'errorPasswordLength'       => 'Slaptažodis turi būti bent {0, number} ženklų ilgio.',
    'suggestPasswordLength'     => 'Prisijungimo frazės - iki 255 ženklų ilgio - yra kur kas saugesni slaptažodžiai kuriuos lengva įsiminti.',
    'errorPasswordCommon'       => 'Slaptažodis neturi būti paprastas žodis.',
    'suggestPasswordCommon'     => 'Slaptažodis buvo patikrintas lyginant jį su daugiau nei 65 tūkst. įprastai naudojamų slaptažodžių ir slaptažodžių, kurie buvo išviešinti nulaužus sistemas.',
    'errorPasswordPersonal'     => 'Slaptažodyje neturi būti įterpta asmeninės informacijos.',
    'suggestPasswordPersonal'   => 'Slaptažodyje neturi būti naudojami menkai pakeisti el. pašto adreso arba vartotojo vardo variantai.',
    'errorPasswordTooSimilar'   => 'Slaptažodis pernelyg panašus į vartotojo vardą.',
    'suggestPasswordTooSimilar' => 'Nenaudokite vartotojo vardo dalių slaptažodyje.',
    'errorPasswordPwned'        => 'Slaptažodis {0} buvo išviešintas po internetinės sistemos nulaužimo ir buvo paskelbtas {1, number} kartus {2} nulaužtų slaptažodžių sąrašuose.',
    'suggestPasswordPwned'      => '{0} neturi būti naudojamas kaip slaptažodis. Jei jį naudojate bet kur, tuoj pat pakeiskite.',
    'errorPasswordEmpty'        => 'Reikia slaptažodžio.',
    'errorPasswordTooLongBytes' => 'Slaptažodis neturi būti ilgesnis nei {param} baitų(-ai).',
    'passwordChangeSuccess'     => 'Slaptažodis sėkmingai pakeistas',
    'userDoesNotExist'          => 'Slaptažodis nepakeistas. Tokio vartotojo nėra',
    'resetTokenExpired'         => 'Deja, Jūsų slaptažodžio atkūrimo raktas nebegalioja.',

    // Email Globals
    'emailInfo'      => 'Šiek tiek informacijos apie asmenį:',
    'emailIpAddress' => 'IP adresas:',
    'emailDevice'    => 'Įrenginys:',
    'emailDate'      => 'Data:',

    // 2FA
    'email2FATitle'       => 'Dviejų faktorių autentifikacija',
    'confirmEmailAddress' => 'Patvirtinkite savo el. pašto adresą.',
    'emailEnterCode'      => 'Patvirtinkite savo el. paštą',
    'emailConfirmCode'    => 'Įrašykite 6 ženklų kodą, kurį ką tik išsiuntėme Jums el. paštu.',
    'email2FASubject'     => 'Jūsų autentifikacijos kodas',
    'email2FAMailBody'    => 'Jūsų autentifikacijos kodas yra:',
    'invalid2FAToken'     => 'Kodas buvo neteisingas.',
    'need2FA'             => 'Turite užbaigti dviejų faktorių autentifikaciją.',
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
    'needVerification'       => 'Norėdami užbaigti paskyros aktyvavimą, patikrinkite savo el. pašto dėžutę.',

    // Activate
    'emailActivateTitle'    => 'Aktyvavimas el. paštu',
    'emailActivateBody'     => 'Mes ką tik išsiuntėme Jums el. laišką su kodu el. pašto adreso patvirtinimui. Nukopijuokite tą kodą ir įterpkite žemiau.',
    'emailActivateSubject'  => 'Jūsų aktyvavimo kodas',
    'emailActivateMailBody' => 'Prašome naudoti žemiau esantį kodą paskyros aktyvavimui. Tuomet galėsite pradėti naudoti mūsų svetainę.',
    'invalidActivateToken'  => 'Kodas buvo neteisingas.',
    'needActivate'          => 'Turite baigti registraciją panaudodami kodą, išsiųstą Jums el. pašto adresu.',
    'activationBlocked'     => 'Prieš prisijungdami turite aktyvuoti paskyrą.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} nėra egzistuojanti grupė.',
    'missingTitle' => 'Grupė turi turėti pavadinimą.',

    // Permissions
    'unknownPermission' => '{0} nėra žinomas leidimo tipas.',

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

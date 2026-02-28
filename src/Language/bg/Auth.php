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
    // Изключения
    'unknownAuthenticator'  => '{0} не е валиден аутентикатор.',
    'unknownUserProvider'   => 'Не може да се определи използваният потребителски доставчик.',
    'invalidUser'           => 'Не може да се намери посоченият потребител.',
    'bannedUser'            => 'Не може да влезете в профила си, тъй като сте баннати.',
    'logOutBannedUser'      => 'Изведен сте от профила ви, защото сте баннати.',
    'badAttempt'            => 'Не може да влезете в профила си. Моля, проверете вашите потребителски данни.',
    'noPassword'            => 'Не може да се потвърди потребителски профил без парола.',
    'invalidPassword'       => 'Не може да влезете в профила си. Моля, проверете вашата парола.',
    'noToken'               => 'Всяка заявка трябва да съдържа носител на токен в {0} заглавната си част.',
    'badToken'              => 'Токенът за достъп не е валиден.',
    'oldToken'              => 'Токенът за достъп е изтекъл.',
    'noUserEntity'          => 'Потребителското съдържание трябва да бъде предоставено за потвърждение на паролата.',
    'invalidEmail'          => 'Не може да се потвърди, че имейл адресът съвпада с имейл адреса от записа.',
    'unableSendEmailToUser' => 'Съжаляваме, имаше проблем с изпращането на имейла. Не можем да изпратим имейл до "{0}".',
    'throttled'             => 'Твърде много заявки са направени от този IP адрес. Може да опитате отново след {0} секунди.',
    'notEnoughPrivilege'    => 'Нямате необходимите права за изпълнение на желаната операция.',
    // JWT Изключения
    'invalidJWT'     => 'Токенът е невалиден.',
    'expiredJWT'     => 'Токенът е изтекъл.',
    'beforeValidJWT' => 'Токенът все още не е наличен.',

    'email'           => 'Адрес на електронна поща',
    'username'        => 'Потребителско име',
    'password'        => 'Парола',
    'passwordConfirm' => 'Парола (отново)',
    'haveAccount'     => 'Вече имате акаунт?',
    'token'           => 'Токен',

    // Бутони
    'confirm' => 'Потвърди',
    'send'    => 'Изпрати',

    // Регистрация
    'register'         => 'Регистрация',
    'registerDisabled' => 'Регистрацията в момента не е позволена.',
    'registerSuccess'  => 'Добре дошли!',

    // Вход
    'login'              => 'Вход',
    'needAccount'        => 'Нуждаете се от акаунт?',
    'rememberMe'         => 'Запомни ме?',
    'forgotPassword'     => 'Забравена парола?',
    'useMagicLink'       => 'Използвайте линк за вход',
    'magicLinkSubject'   => 'Вашият линк за вход',
    'magicTokenNotFound' => 'Не може да се потвърди линка.',
    'magicLinkExpired'   => 'Съжаляваме, линкът е изтекъл.',
    'checkYourEmail'     => 'Проверете вашия имейл!',
    'magicLinkDetails'   => 'Току що ви изпратихме имейл с линк за вход. Линкът ще бъде валиден само {0} минути.',
    'magicLinkDisabled'  => 'Използването на линк за вход в момента не е разрешено.',
    'successLogout'      => 'Успешно излязохте от системата.',
    'backToLogin'        => 'Обратно към входа',

    // Пароли
    'errorPasswordLength'       => 'Паролите трябва да са поне {0, number} символа дълги.',
    'suggestPasswordLength'     => 'Паролите с дължина до 255 символа, наричани "паролни изречения", правят паролите по-сигурни и лесни за запомняне.',
    'errorPasswordCommon'       => 'Паролата не трябва да е общоизвестна.',
    'suggestPasswordCommon'     => 'Проверихме паролата срещу над 65 000 общоизвестни пароли или пароли, които са били изложени след хакерски атаки.',
    'errorPasswordPersonal'     => 'Паролите не могат да съдържат лична информация.',
    'suggestPasswordPersonal'   => 'Вариации на имейл адреса или потребителското име не трябва да се използват за пароли.',
    'errorPasswordTooSimilar'   => 'Паролата е твърде подобна на потребителското име.',
    'suggestPasswordTooSimilar' => 'Не използвайте части от потребителското си име в паролата си.',
    'errorPasswordPwned'        => 'Паролата {0} е била компрометирана в следствие на нарушения в сигурността на данните и е била видяна {1, number} пъти в {2} от компрометираните пароли.',
    'suggestPasswordPwned'      => '{0} никога не трябва да се използва като парола. Ако я използвате някъде, трябва да я сменете веднага.',
    'errorPasswordEmpty'        => 'Изисква се парола.',
    'errorPasswordTooLongBytes' => 'Паролата не може да бъде по-дълга от {param} байта.',
    'passwordChangeSuccess'     => 'Паролата беше успешно променена.',
    'userDoesNotExist'          => 'Паролата не беше променена. Потребителят не съществува.',
    'resetTokenExpired'         => 'Съжаляваме. Вашият токен за нулиране на паролата е изтекъл.',

    // Глобални променливи за електронна поща
    'emailInfo'      => 'Информации за потребител:',
    'emailIpAddress' => 'IP Адрес:',
    'emailDevice'    => 'Устройство:',
    'emailDate'      => 'Дата:',

    // Двуфакторна автентикация (2FA)
    'email2FATitle'       => 'Двуфакторна автентикация',
    'confirmEmailAddress' => 'Потвърдете Вашата електронна поща.',
    'emailEnterCode'      => 'Потвърдете Вашата електронна поща',
    'emailConfirmCode'    => 'Въведете 6-цифрен код, който изпратихме на Вашата електронна поща.',
    'email2FASubject'     => 'Вашият код за автентикация',
    'email2FAMailBody'    => 'Вашият код за автентикация е:',
    'invalid2FAToken'     => 'Грешен код.',
    'need2FA'             => 'Трябва да завършите двуфакторна верификация.',
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
    'needVerification'       => 'Проверете Вашата електронна поща, за да завършите активацията на профила.',

    // Активация
    'emailActivateTitle'    => 'Активиране по имейл',
    'emailActivateBody'     => 'Изпратихме ви имейл с код за потвърждение на вашия имейл адрес. Копирайте този код и го поставете по-долу.',
    'emailActivateSubject'  => 'Вашият код за активация',
    'emailActivateMailBody' => 'Моля, използвайте по-долу посочения код за активиране на акаунта си и започнете да използвате сайта.',
    'invalidActivateToken'  => 'Кода е невалиден.',
    'needActivate'          => 'Трябва да завършите регистрацията си, като потвърдите кода, изпратен на вашия имейл адрес.',
    'activationBlocked'     => 'Трябва да активирате акаунта си, преди да влезете.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Групи
    'unknownGroup' => '{0} не е валидна група.',
    'missingTitle' => 'Групите трябва да имат заглавие.',

    // Разрешения
    'unknownPermission' => '{0} не е валидно разрешение.',

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

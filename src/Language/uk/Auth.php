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
    'unknownAuthenticator'  => '{0} не є дійсним автентифікатором.',
    'unknownUserProvider'   => 'Неможливо визначити постачальника користувача для використання.',
    'invalidUser'           => 'Неможливо знайти вказаного користувача.',
    'bannedUser'            => 'Неможливо увійти, оскільки ви зараз заблоковані.',
    'logOutBannedUser'      => 'Ви вийшли з системи, оскільки вас заблокували.',
    'badAttempt'            => 'Неможливо увійти. Перевірте свої облікові дані.',
    'noPassword'            => 'Неможливо перевірити користувача без пароля.',
    'invalidPassword'       => 'Неможливо увійти. Перевірте свій пароль.',
    'noToken'               => 'Кожен запит повинен мати токен носія в заголовку {0}.',
    'badToken'              => 'Токен доступу недійсний.',
    'oldToken'              => 'Термін дії токена доступу минув.',
    'noUserEntity'          => 'Потрібно вказати сутність користувача для підтвердження пароля.',
    'invalidEmail'          => 'Неможливо перевірити, чи адреса електронної пошти відповідає зареєстрованій.',
    'unableSendEmailToUser' => 'Вибачте, під час надсилання електронного листа виникла проблема. Не вдалося надіслати електронний лист на "{0}".',
    'throttled'             => 'Із цієї IP-адреси зроблено забагато запитів. Ви можете спробувати ще раз через {0} секунд.',
    'notEnoughPrivilege'    => 'У вас немає необхідного дозволу для виконання потрібної операції.',
    // JWT Exceptions
    'invalidJWT'     => 'Токен недійсний.',
    'expiredJWT'     => 'Термін дії токена минув.',
    'beforeValidJWT' => 'Токен ще не доступний.',

    'email'           => 'Адреса електронної пошти',
    'username'        => 'Ім’я користувача',
    'password'        => 'Пароль',
    'passwordConfirm' => 'Пароль (ще раз)',
    'haveAccount'     => 'Вже є обліковий запис?',
    'token'           => 'Токен',

    // Buttons
    'confirm' => 'Підтвердити',
    'send'    => 'Надіслати',

    // Registration
    'register'         => 'Зареєструватися',
    'registerDisabled' => 'Реєстрація зараз не дозволена.',
    'registerSuccess'  => 'Ласкаво просимо на борт!',

    // Login
    'login'              => 'Вхід',
    'needAccount'        => 'Потрібен обліковий запис?',
    'rememberMe'         => 'Запам’ятати мене',
    'forgotPassword'     => 'Забули пароль?',
    'useMagicLink'       => 'Скористайтеся посиланням для входу',
    'magicLinkSubject'   => 'Ваше посилання для входу',
    'magicTokenNotFound' => 'Неможливо перевірити посилання.',
    'magicLinkExpired'   => 'Вибачте, термін дії посилання закінчився.',
    'checkYourEmail'     => 'Перевірте свою електронну пошту!',
    'magicLinkDetails'   => 'Ми щойно надіслали вам електронний лист із посиланням для входу. Він дійсний лише протягом {0} хвилин.',
    'magicLinkDisabled'  => 'Використання посилання для входу зараз заборонене.',
    'successLogout'      => 'Ви успішно вийшли.',
    'backToLogin'        => 'Повернутися до входу',

    // Passwords
    'errorPasswordLength'       => 'Паролі повинні містити принаймні {0, number} символів.',
    'suggestPasswordLength'     => 'Паролі до 255 символів створюють надійніші паролі, які легко запам’ятати.',
    'errorPasswordCommon'       => 'Пароль не має бути звичайним.',
    'suggestPasswordCommon'     => 'Пароль звірено із більш ніж 65 тисячами часто використовуваних паролів або паролів, які були розкриті через хакерські атаки.',
    'errorPasswordPersonal'     => 'Паролі не можуть містити повторно хешовану особисту інформацію.',
    'suggestPasswordPersonal'   => 'Варіації вашої адреси електронної пошти або імені користувача не повинні використовувати для паролів.',
    'errorPasswordTooSimilar'   => 'Пароль занадто схожий на ім’я користувача.',
    'suggestPasswordTooSimilar' => 'Не використовуйте частини свого імені користувача в паролі.',
    'errorPasswordPwned'        => 'Пароль {0} було розкрито внаслідок витоку даних і було виявлено {1, number} разів у {2} зламаних паролів.',
    'suggestPasswordPwned'      => '{0} ніколи не слід використовувати як пароль. Якщо ви вже використовуєте його десь, негайно змініть його.',
    'errorPasswordEmpty'        => 'Необхідно ввести пароль.',
    'errorPasswordTooLongBytes' => 'Довжина пароля не може перевищувати {param} байт.',
    'passwordChangeSuccess'     => 'Пароль успішно змінено',
    'userDoesNotExist'          => 'Пароль не змінено. Користувач не існує',
    'resetTokenExpired'         => 'Вибачте. Термін дії вашого токена скидання минув.',

    // Email Globals
    'emailInfo'      => 'Деяка відомості про особу:',
    'emailIpAddress' => 'IP-адреса:',
    'emailDevice'    => 'Пристрій:',
    'emailDate'      => 'Дата:',

    // 2FA
    'email2FATitle'       => 'Двофакторна автентифікація',
    'confirmEmailAddress' => 'Підтвердьте адресу електронної пошти.',
    'emailEnterCode'      => 'Підтвердьте свій Email',
    'emailConfirmCode'    => 'Введіть 6-значний код, який ми щойно надіслали на вашу адресу електронної пошти.',
    'email2FASubject'     => 'Ваш код автентифікації',
    'email2FAMailBody'    => 'Ваш код автентифікації:',
    'invalid2FAToken'     => 'Код недійсний.',
    'need2FA'             => 'Ви повинні пройти двофакторну перевірку.',
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
    'needVerification'       => 'Перевірте свою електронну пошту, щоб завершити активацію облікового запису.',

    // Activate
    'emailActivateTitle'    => 'Активація електронної пошти',
    'emailActivateBody'     => 'Ми щойно надіслали вам електронний лист із кодом для підтвердження вашої електронної адреси. Скопіюйте цей код і вставте його нижче.',
    'emailActivateSubject'  => 'Ваш код активації',
    'emailActivateMailBody' => 'Будь ласка, використовуйте наведений нижче код, щоб активувати свій обліковий запис і почати користуватися сайтом.',
    'invalidActivateToken'  => 'Код був невірний.',
    'needActivate'          => 'Ви повинні завершити реєстрацію, підтвердивши код, надісланий на вашу електронну адресу.',
    'activationBlocked'     => 'Ви повинні активувати свій обліковий запис перед входом.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} недійсна група.',
    'missingTitle' => 'Групи повинні мати назву.',

    // Permissions
    'unknownPermission' => '{0} недійсний дозвіл.',

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

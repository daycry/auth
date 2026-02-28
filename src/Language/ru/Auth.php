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
    'unknownAuthenticator'  => '{0} не является действительным аутентификатором.',
    'unknownUserProvider'   => 'Не удалось определить поставщика пользователей.',
    'invalidUser'           => 'Не удалось найти указанного пользователя.',
    'bannedUser'            => 'Невозможно войти, так как вы заблокированы.',
    'logOutBannedUser'      => 'Вы вышли из системы, так как вас заблокировали.',
    'badAttempt'            => 'Не удалось войти. Проверьте свои учётные данные.',
    'noPassword'            => 'Невозможно проверить пользователя без пароля.',
    'invalidPassword'       => 'Не удалось войти. Проверьте свой пароль.',
    'noToken'               => 'У каждого запроса должен быть токен носителя в заголовке {0}.',
    'badToken'              => 'Токен доступа недействителен.',
    'oldToken'              => 'Срок действия токена доступа истёк.',
    'noUserEntity'          => 'Для проверки пароля необходимо предоставить сущность пользователя.',
    'invalidEmail'          => 'Не удалось подтвердить, что адрес электронной почты соответствует зарегистрированному.',
    'unableSendEmailToUser' => 'Извините, возникла проблема с отправкой электронного письма. Не удалось отправить электронное письмо на "{0}".',
    'throttled'             => 'С этого IP-адреса было сделано слишком много запросов. Вы можете попробовать снова через {0} секунд.',
    'notEnoughPrivilege'    => 'У вас нет необходимых разрешений для выполнения требуемой операции.',
    // JWT Exceptions
    'invalidJWT'     => 'Токен недействителен.',
    'expiredJWT'     => 'Срок действия токена истёк.',
    'beforeValidJWT' => 'Токен ещё не доступен.',

    'email'           => 'Адрес электронной почты',
    'username'        => 'Имя пользователя',
    'password'        => 'Пароль',
    'passwordConfirm' => 'Пароль (ещё раз)',
    'haveAccount'     => 'Уже есть учётная запись?',
    'token'           => 'Токен',

    // Buttons
    'confirm' => 'Подтвердить',
    'send'    => 'Отправить',

    // Registration
    'register'         => 'Зарегистрироваться',
    'registerDisabled' => 'Регистрация в настоящее время запрещена.',
    'registerSuccess'  => 'Добро пожаловать на борт!',

    // Login
    'login'              => 'Вход',
    'needAccount'        => 'Нужна учётная запись?',
    'rememberMe'         => 'Запомнить меня',
    'forgotPassword'     => 'Забыли пароль?',
    'useMagicLink'       => 'Воспользуйтесь ссылкой для входа',
    'magicLinkSubject'   => 'Ваша ссылка для входа',
    'magicTokenNotFound' => 'Не удалось проверить ссылку.',
    'magicLinkExpired'   => 'Извините, срок действия ссылки истёк.',
    'checkYourEmail'     => 'Проверьте свою электронную почту!',
    'magicLinkDetails'   => 'Мы только что отправили вам электронное письмо со ссылкой для входа. Она действительна только в течение {0} минут.',
    'magicLinkDisabled'  => 'Использование ссылки для входа в настоящее время запрещено.',
    'successLogout'      => 'Вы успешно вышли.',
    'backToLogin'        => 'Вернуться ко входу',

    // Passwords
    'errorPasswordLength'       => 'Пароли должны содержать не менее {0, number} символов.',
    'suggestPasswordLength'     => 'Фразы длиной до 255 символов являются более надёжными паролями, которые легко запомнить.',
    'errorPasswordCommon'       => 'Пароль не должен быть распространённым.',
    'suggestPasswordCommon'     => 'Пароль был проверен на соответствие более чем 65 тысячам часто используемых паролей или паролей, которые были раскрыты в результате хакерских атак.',
    'errorPasswordPersonal'     => 'Пароли не могут содержать повторно хешированную личную информацию.',
    'suggestPasswordPersonal'   => 'Вариации вашего адреса электронной почты или имени пользователя не следует использовать для паролей.',
    'errorPasswordTooSimilar'   => 'Пароль слишком похож на имя пользователя.',
    'suggestPasswordTooSimilar' => 'Не используйте части вашего имени пользователя в пароле.',
    'errorPasswordPwned'        => 'Пароль {0} был раскрыт в результате утечки данных и был обнаружен {1, number} раз в {2} скомпрометированных паролях.',
    'suggestPasswordPwned'      => '{0} никогда не следует использовать в качестве пароля. Если вы уже используете его где-либо, немедленно измените его.',
    'errorPasswordEmpty'        => 'Требуется пароль.',
    'errorPasswordTooLongBytes' => 'Длина пароля не может превышать {param} байт.',
    'passwordChangeSuccess'     => 'Пароль успешно изменён',
    'userDoesNotExist'          => 'Пароль не изменён. Пользователь не существует',
    'resetTokenExpired'         => 'К сожалению, срок действия вашего токена сброса истёк.',

    // Email Globals
    'emailInfo'      => 'Некоторые сведения о человеке:',
    'emailIpAddress' => 'IP-адрес:',
    'emailDevice'    => 'Устройство:',
    'emailDate'      => 'Дата:',

    // 2FA
    'email2FATitle'       => 'Двухфакторная аутентификация',
    'confirmEmailAddress' => 'Подтвердите свой адрес электронной почты.',
    'emailEnterCode'      => 'Подтвердите свой Email',
    'emailConfirmCode'    => 'Введите 6-значный код, который мы только что отправили на ваш адрес электронной почты.',
    'email2FASubject'     => 'Ваш код аутентификации',
    'email2FAMailBody'    => 'Ваш код аутентификации:',
    'invalid2FAToken'     => 'Код неверный.',
    'need2FA'             => 'Вы должны пройти двухфакторную проверку.',
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
    'needVerification'       => 'Проверьте свою электронную почту, чтобы завершить активацию учётной записи.',

    // Activate
    'emailActivateTitle'    => 'Активация электронной почты',
    'emailActivateBody'     => 'Мы только что отправили вам электронное письмо с кодом для подтверждения вашего адреса электронной почты. Скопируйте этот код и вставьте его ниже.',
    'emailActivateSubject'  => 'Ваш код активации',
    'emailActivateMailBody' => 'Пожалуйста, воспользуйтесь приведённым ниже кодом для активации учетной записи и начала работы с сайтом.',
    'invalidActivateToken'  => 'Код неверный.',
    'needActivate'          => 'Вы должны завершить регистрацию, подтвердив код, отправленный на ваш адрес электронной почты.',
    'activationBlocked'     => 'Вы должны активировать свою учетную запись перед входом в систему.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} не является действительной группой.',
    'missingTitle' => 'Группы должны иметь название.',

    // Permissions
    'unknownPermission' => '{0} не является действительным разрешением.',

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

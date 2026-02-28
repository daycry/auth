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
    // Excepciones
    'unknownAuthenticator'  => '{0} no es un autenticador válido.',
    'unknownUserProvider'   => 'No se puede determinar el proveedor de usuario a utilizar.',
    'invalidUser'           => 'No se puede localizar al usuario especificado.',
    'bannedUser'            => 'No puedes iniciar sesión ya que estás actualmente vetado.',
    'logOutBannedUser'      => 'Se ha cerrado la sesión porque se ha vetado al usuario.',
    'badAttempt'            => 'No se puede iniciar sesión. Por favor, comprueba tus credenciales.',
    'noPassword'            => 'No se puede validar un usuario sin contraseña.',
    'invalidPassword'       => 'No se puede iniciar sesión. Por favor, comprueba tu contraseña.',
    'noToken'               => 'Cada solicitud debe tener un token de portador en la cabecera {0}.',
    'badToken'              => 'El token de acceso no es válido.',
    'oldToken'              => 'El token de acceso ha caducado.',
    'noUserEntity'          => 'Se debe proporcionar una entidad de usuario para la validación de contraseña.',
    'invalidEmail'          => 'No se puede verificar que la dirección de correo electrónico coincida con la registrada.',
    'unableSendEmailToUser' => 'Lo siento, hubo un problema al enviar el correo electrónico. No pudimos enviar un correo electrónico a "{0}".',
    'throttled'             => 'Se han realizado demasiadas solicitudes desde esta dirección IP. Puedes intentarlo de nuevo en {0} segundos.',
    'notEnoughPrivilege'    => 'No tienes los permisos necesarios para realizar la operación deseada.',
    // JWT Exceptions
    'invalidJWT'     => '(To be translated) The token is invalid.',
    'expiredJWT'     => '(To be translated) The token has expired.',
    'beforeValidJWT' => '(To be translated) The token is not yet available.',

    'email'           => 'Correo Electrónico',
    'username'        => 'Nombre de usuario',
    'password'        => 'Contraseña',
    'passwordConfirm' => 'Contraseña (otra vez)',
    'haveAccount'     => '¿Ya tienes una cuenta?',
    'token'           => '(To be translated) Token',

    // Botones
    'confirm' => 'Confirmar',
    'send'    => 'Enviar',

    // Registro
    'register'         => 'Registrarse',
    'registerDisabled' => 'Actualmente no se permite el registro.',
    'registerSuccess'  => '¡Bienvenido a bordo!',

    // Login
    'login'              => 'Iniciar sesión',
    'needAccount'        => '¿Necesitas una cuenta?',
    'rememberMe'         => 'Recordarme',
    'forgotPassword'     => '¿Olvidaste tu contraseña',
    'useMagicLink'       => 'Usar un enlace de inicio de sesión',
    'magicLinkSubject'   => 'Tu enlace de inicio de sesión',
    'magicTokenNotFound' => 'No se puede verificar el enlace.',
    'magicLinkExpired'   => 'Lo siento, el enlace ha caducado.',
    'checkYourEmail'     => '¡Revisa tu correo electrónico!',
    'magicLinkDetails'   => 'Acabamos de enviarte un correo electrónico con un enlace de inicio de sesión. Solo es válido durante {0} minutos.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Has cerrado sesión correctamente.',
    'backToLogin'        => 'Volver al inicio de sesión',

    // Contraseñas
    'errorPasswordLength'       => 'Las contraseñas deben tener al menos {0, number} caracteres.',
    'suggestPasswordLength'     => 'Las frases de contraseña, de hasta 255 caracteres de longitud, hacen que las contraseñas sean más seguras y fáciles de recordar.',
    'errorPasswordCommon'       => 'La contraseña no puede ser una contraseña común.',
    'suggestPasswordCommon'     => 'La contraseña se comprobó frente a más de 65k contraseñas comúnmente utilizadas o contraseñas que se filtraron a través de ataques.',
    'errorPasswordPersonal'     => 'Las contraseñas no pueden contener información personal reutilizada.',
    'suggestPasswordPersonal'   => 'No se deben usar variaciones de su dirección de correo electrónico o nombre de usuario como contraseña.',
    'errorPasswordTooSimilar'   => 'La contraseña es demasiado similar al nombre de usuario.',
    'suggestPasswordTooSimilar' => 'No use partes de su nombre de usuario en su contraseña.',
    'errorPasswordPwned'        => 'La contraseña {0} se ha expuesto debido a una violación de datos y se ha visto {1, number} veces en {2} de contraseñas comprometidas.',
    'suggestPasswordPwned'      => 'Nunca se debe usar {0} como contraseña. Si lo está utilizando en algún lugar, cambie su contraseña de inmediato.',
    'errorPasswordEmpty'        => 'Se requiere una contraseña.',
    'errorPasswordTooLongBytes' => 'La contraseña no puede tener más de {param} caracteres',
    'passwordChangeSuccess'     => 'Contraseña cambiada correctamente',
    'userDoesNotExist'          => 'La contraseña no se cambió. El usuario no existe',
    'resetTokenExpired'         => 'Lo siento. Su token de reinicio ha caducado.',

    // Email Globals
    'emailInfo'      => 'Alguna información sobre la persona:',
    'emailIpAddress' => 'Dirección IP:',
    'emailDevice'    => 'Dispositivo:',
    'emailDate'      => 'Fecha:',

    // 2FA
    'email2FATitle'       => 'Autenticación de dos factores',
    'confirmEmailAddress' => 'Confirma tu dirección de correo electrónico.',
    'emailEnterCode'      => 'Confirma tu correo electrónico',
    'emailConfirmCode'    => 'Ingresa el código de 6 dígitos que acabamos de enviar a tu correo electrónico.',
    'email2FASubject'     => 'Tu código de autenticación',
    'email2FAMailBody'    => 'Tu código de autenticación es:',
    'invalid2FAToken'     => 'El código era incorrecto.',
    'need2FA'             => 'Debes completar la verificación de dos factores.',
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
    'needVerification'       => 'Verifica tu correo electrónico para completar la activación de la cuenta.',

    // Activar
    'emailActivateTitle'    => 'Activación de correo electrónico',
    'emailActivateBody'     => 'Acabamos de enviarte un correo electrónico con un código para confirmar tu dirección de correo electrónico. Copia ese código y pégalo a continuación.',
    'emailActivateSubject'  => 'Tu código de activación',
    'emailActivateMailBody' => 'Utiliza el código siguiente para activar tu cuenta y comenzar a usar el sitio.',
    'invalidActivateToken'  => 'El código era incorrecto.',
    'needActivate'          => 'Debes completar tu registro confirmando el código enviado a tu dirección de correo electrónico.',
    'activationBlocked'     => 'Debes activar tu cuenta antes de iniciar sesión.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Grupos
    'unknownGroup' => '{0} no es un grupo válido.',
    'missingTitle' => 'Los grupos deben tener un título.',

    // Permisos
    'unknownPermission' => '{0} no es un permiso válido.',

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

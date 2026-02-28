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
    'unknownAuthenticator'  => '{0} não é um autenticador válido.',
    'unknownUserProvider'   => 'Não foi possível determinar o provedor de usuário a ser usado.',
    'invalidUser'           => 'Não foi possível localizar o usuário especificado.',
    'bannedUser'            => 'Não é possível fazer login porque você está banido no momento.',
    'logOutBannedUser'      => 'Você foi desconectado porque foi banido.',
    'badAttempt'            => 'Não foi possível fazer login. Por favor, verifique suas credenciais.',
    'noPassword'            => 'Não é possível validar um usuário sem uma senha.',
    'invalidPassword'       => 'Não foi possível fazer login. Por favor, verifique sua senha.',
    'noToken'               => 'Toda requisição deve ter um token portador no cabeçalho {0}.',
    'badToken'              => 'O token de acesso é inválido.',
    'oldToken'              => 'O token de acesso expirou.',
    'noUserEntity'          => 'A entidade de usuário deve ser fornecida para validação de senha.',
    'invalidEmail'          => 'Não foi possível verificar se o endereço de email corresponde ao e-mail registrado.',
    'unableSendEmailToUser' => 'Desculpe, houve um problema ao enviar o email. Não pudemos enviar um email para {0}.',
    'throttled'             => 'Muitas solicitações feitas a partir deste endereço IP. Você pode tentar novamente em {0} segundos.',
    'notEnoughPrivilege'    => 'Você não tem a permissão necessária para realizar a operação desejada.',
    // JWT Exceptions
    'invalidJWT'     => 'O token é inválido.',
    'expiredJWT'     => 'O token expirou.',
    'beforeValidJWT' => 'O token ainda não está disponível.',

    'email'           => 'Endereço de Email',
    'username'        => 'Nome de usuário',
    'password'        => 'Senha',
    'passwordConfirm' => 'Senha (novamente)',
    'haveAccount'     => 'Já tem uma conta?',
    'token'           => '(To be translated) Token',

    // Botões
    'confirm' => 'Confirmar',
    'send'    => 'Enviar',

    // Registro
    'register'         => 'Registrar',
    'registerDisabled' => 'O registro não está permitido no momento.',
    'registerSuccess'  => 'Bem-vindo a bordo!',

    // Login
    'login'              => 'Login',
    'needAccount'        => 'Precisa de uma conta?',
    'rememberMe'         => 'Lembrar de mim?',
    'forgotPassword'     => 'Esqueceu sua senha?',
    'useMagicLink'       => 'Use um Link de Login',
    'magicLinkSubject'   => 'Seu Link de Login',
    'magicTokenNotFound' => 'Não foi possível verificar o link.',
    'magicLinkExpired'   => 'Desculpe, o link expirou.',
    'checkYourEmail'     => 'Verifique seu e-mail!',
    'magicLinkDetails'   => 'Acabamos de enviar um e-mail com um link de Login. Ele é válido apenas por {0} minutos.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Você saiu com sucesso.',
    'backToLogin'        => 'Voltar para o login',

    // Senhas
    'errorPasswordLength'       => 'As senhas devem ter pelo menos {0, number} caracteres.',
    'suggestPasswordLength'     => 'Frases de senha - até 255 caracteres - criam senhas mais seguras que são fáceis de lembrar.',
    'errorPasswordCommon'       => 'A senha não deve ser uma senha comum.',
    'suggestPasswordCommon'     => 'A senha foi verificada contra mais de 65k senhas comuns ou senhas que foram vazadas por invasões.',
    'errorPasswordPersonal'     => 'As senhas não podem conter informações pessoais re-criptografadas.',
    'suggestPasswordPersonal'   => 'Variações do seu endereço de e-mail ou nome de usuário não devem ser usadas como senhas.',
    'errorPasswordTooSimilar'   => 'A senha é muito semelhante ao nome de usuário.',
    'suggestPasswordTooSimilar' => 'Não use partes do seu nome de usuário na sua senha.',
    'errorPasswordPwned'        => 'A senha {0} foi exposta devido a uma violação de dados e foi vista {1, number} vezes em {2} de senhas comprometidas.',
    'suggestPasswordPwned'      => '{0} nunca deve ser usado como uma senha. Se você estiver usando em algum lugar, altere imediatamente.',
    'errorPasswordEmpty'        => 'É necessária uma senha.',
    'errorPasswordTooLongBytes' => 'A senha não pode exceder {param} bytes.',
    'passwordChangeSuccess'     => 'Senha alterada com sucesso',
    'userDoesNotExist'          => 'Senha não foi alterada. Usuário não existe',
    'resetTokenExpired'         => 'Desculpe. Seu token de redefinição expirou.',

    // E-mails Globais
    'emailInfo'      => 'Algumas informações sobre a pessoa:',
    'emailIpAddress' => 'Endereço IP:',
    'emailDevice'    => 'Dispositivo:',
    'emailDate'      => 'Data:',

    // 2FA
    'email2FATitle'       => 'Autenticação de dois fatores',
    'confirmEmailAddress' => 'Confirme seu endereço de e-mail.',
    'emailEnterCode'      => 'Confirme seu email',
    'emailConfirmCode'    => 'Insira o código de 6 dígitos que acabamos de enviar para seu endereço de e-mail.',
    'email2FASubject'     => 'Seu código de autenticação',
    'email2FAMailBody'    => 'Seu código de autenticação é:',
    'invalid2FAToken'     => 'O código estava incorreto.',
    'need2FA'             => 'Você deve concluir uma verificação de dois fatores.',
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
    'needVerification'       => 'Verifique seu e-mail para concluir a ativação da conta.',

    // Ativar
    'emailActivateTitle'    => 'Ativação de email',
    'emailActivateBody'     => 'Acabamos de enviar um email para você com um código para confirmar seu endereço de e-mail. Copie esse código e cole abaixo.',
    'emailActivateSubject'  => 'Seu código de ativação',
    'emailActivateMailBody' => 'Use o código abaixo para ativar sua conta e começar a usar o site.',
    'invalidActivateToken'  => 'O código estava incorreto.',
    'needActivate'          => 'Você deve concluir seu registro confirmando o código enviado para seu endereço de e-mail.',
    'activationBlocked'     => 'Você deve ativar sua conta antes de fazer o login.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Grupos
    'unknownGroup' => '{0} não é um grupo válido.',
    'missingTitle' => 'Os grupos devem ter um título.',

    // Permissões
    'unknownPermission' => '{0} não é uma permissão válida.',

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

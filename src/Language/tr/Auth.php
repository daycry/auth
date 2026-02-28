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
    'unknownAuthenticator'  => '{0} geçerli bir kimlik doğrulayıcı değil.',
    'unknownUserProvider'   => 'Kullanılacak Kullanıcı Sağlayıcı belirlenemiyor.',
    'invalidUser'           => 'Belirtilen kullanıcı bulunamadı.',
    'bannedUser'            => 'Bu hesap yasaklandı. Şu anda giriş yapamazsınız.',
    'logOutBannedUser'      => 'Bu hesap yasaklandığından dolayı oturumunuz kapatıldı.',
    'badAttempt'            => 'Oturumunuz açılamıyor. Lütfen kimlik bilgilerinizi kontrol edin.',
    'noPassword'            => 'Parola olmadan bir kullanıcı doğrulanamaz.',
    'invalidPassword'       => 'Oturumunuz açılamıyor. Lütfen şifrenizi kontrol edin.',
    'noToken'               => 'Her istediğin başlığında {0} bearer anahtar belirteci olmalıdır.',
    'badToken'              => 'Erişim anahtarı geçersiz.',
    'oldToken'              => 'Erişim anahtarının süresi doldu.',
    'noUserEntity'          => 'Parola doğrulaması için Kullanıcı Varlığı sağlanmalıdır.',
    'invalidEmail'          => 'E-posta adresinin kayıtlı e-posta ile eşleştiği doğrulanamıyor.',
    'unableSendEmailToUser' => 'Üzgünüz, e-posta gönderilirken bir sorun oluştu. "{0}" adresine e-posta gönderemedik.',
    'throttled'             => 'Bu IP adresinden çok fazla istek yapıldı. {0} saniye sonra tekrar deneyebilirsiniz.',
    'notEnoughPrivilege'    => 'İstediğiniz işlemi gerçekleştirmek için gerekli izne sahip değilsiniz.',
    // JWT Exceptions
    'invalidJWT'     => 'Token geçersiz.',
    'expiredJWT'     => 'Tokenin süresi dolmuş.',
    'beforeValidJWT' => 'Token henüz geçerli değil.',

    'email'           => 'E-posta Adresi',
    'username'        => 'Kullanıcı Adı',
    'password'        => 'Şifre',
    'passwordConfirm' => 'Şifre (tekrar)',
    'haveAccount'     => 'Zaten hesabınız var mı?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Onayla',
    'send'    => 'Gönder',

    // Registration
    'register'         => 'Kayıt Ol',
    'registerDisabled' => 'Kayıt işlemine şu anda izin verilmiyor.',
    'registerSuccess'  => 'Aramıza Hoşgeldiniz!',

    // Login
    'login'              => 'Giriş',
    'needAccount'        => 'Bir hesaba mı ihtiyacınız var?',
    'rememberMe'         => 'Beni hatırla?',
    'forgotPassword'     => 'Şifrenizi mı unuttunuz?',
    'useMagicLink'       => 'Giriş Bağlantısı Kullanın',
    'magicLinkSubject'   => 'Giriş Bağlantınız',
    'magicTokenNotFound' => 'Bağlantı doğrulanamıyor.',
    'magicLinkExpired'   => 'Üzgünüm, bağlantının süresi doldu.',
    'checkYourEmail'     => 'E-postanı kontrol et!',
    'magicLinkDetails'   => 'Az önce size içinde bir Giriş bağlantısı olan bir e-posta gönderdik. Bağlantı {0} dakika için geçerlidir.',
    'magicLinkDisabled'  => '(To be translated) Use of MagicLink is currently not allowed.',
    'successLogout'      => 'Başarıyla çıkış yaptınız.',
    'backToLogin'        => 'Girişe Geri Dön',

    // Passwords
    'errorPasswordLength'       => 'Şifre en az {0, number} karakter uzunluğunda olmalıdır.',
    'suggestPasswordLength'     => 'En fazla 255 karakter uzunluğundaki geçiş ifadeleri, hatırlaması kolay, daha güvenli şifreler oluşturur.',
    'errorPasswordCommon'       => 'Şifre genel bir şifre olmamalıdır.',
    'suggestPasswordCommon'     => 'Şifre, yaygın olarak kullanılan 65 binden fazla şifre veya bilgisayar korsanlığı yoluyla sızdırılmış şifreler açısından kontrol edildi.',
    'errorPasswordPersonal'     => 'Parolalar, yeniden oluşturulmuş kişisel bilgileri içeremez.',
    'suggestPasswordPersonal'   => 'E-posta adresiniz veya kullanıcı adınızdaki varyasyonlar, şifreler için kullanılmamalıdır.',
    'errorPasswordTooSimilar'   => 'Şifre, kullanıcı adınıza çok benziyor.',
    'suggestPasswordTooSimilar' => 'Kullanıcı adınızın bazı kısımlarını şifrenizde kullanmayın.',
    'errorPasswordPwned'        => '{0} şifresi, bir veri ihlali nedeniyle açığa çıktı ve güvenliği ihlal edilmiş şifrelerin {2} tanesinde {1, number} kez görüldü.',
    'suggestPasswordPwned'      => '{0} asla şifre olarak kullanılmamalıdır. Herhangi bir yerde kullanıyorsanız hemen değiştirin.',
    'errorPasswordEmpty'        => 'Şifre gerekli.',
    'errorPasswordTooLongBytes' => 'Şifre uzunluğu {param} baytı geçemez.',
    'passwordChangeSuccess'     => 'Şifre başarıyla değiştirildi.',
    'userDoesNotExist'          => 'Şifre değiştirilmedi. Kullanıcı yok.',
    'resetTokenExpired'         => 'Üzgünüz. Sıfırlama anahtarınızın süresi doldu.',

    // Email Globals
    'emailInfo'      => 'Kişi hakkında bazı bilgiler:',
    'emailIpAddress' => 'IP Adresi:',
    'emailDevice'    => 'Cihaz:',
    'emailDate'      => 'Tarih:',

    // 2FA
    'email2FATitle'       => 'İki Faktörlü Kimlik Doğrulama',
    'confirmEmailAddress' => 'E-Posta adresini onayla.',
    'emailEnterCode'      => 'E-posta adresinizi onaylayın.',
    'emailConfirmCode'    => 'Az önce e-posta adresinize gönderdiğimiz 6 haneli kodu girin.',
    'email2FASubject'     => 'Kimlik doğrulama kodunuz',
    'email2FAMailBody'    => 'Kimlik doğrulama kodunuz:',
    'invalid2FAToken'     => 'Kod yanlış.',
    'need2FA'             => 'İki faktörlü doğrulamayı tamamlamanız gerekir.',
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
    'needVerification'       => 'Hesap aktivasyonunu tamamlamak için e-postanızı kontrol edin.',

    // Activate
    'emailActivateTitle'    => 'E-Posta Aktivasyonu',
    'emailActivateBody'     => 'Az önce size e-posta adresinizi doğrulamak için bir kod içeren bir e-posta gönderdik. Bu kodu kopyalayın ve aşağıya yapıştırın.',
    'emailActivateSubject'  => 'Aktivasyon kodunuz',
    'emailActivateMailBody' => 'Hesabınızı etkinleştirmek ve siteyi kullanmaya başlamak için lütfen aşağıdaki kodu kullanın.',
    'invalidActivateToken'  => 'Kod yanlıştı.',
    'needActivate'          => 'E-posta adresinize gönderilen kodu onaylayarak kaydınızı tamamlamanız gerekmektedir.',
    'activationBlocked'     => 'Giriş yapmadan önce hesabınızı etkinleştirmeniz gerekmektedir.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} geçerli bir grup değil.',
    'missingTitle' => 'Grupların bir başlığı olmalıdır.',

    // Permissions
    'unknownPermission' => '{0} geçerli bir izin değil.',

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

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
    'unknownAuthenticator'  => '{0} bukan otentikator yang sah.',
    'unknownUserProvider'   => 'Tidak dapat menentukan Penyedia Pengguna yang akan digunakan.',
    'invalidUser'           => 'Tidak dapat menemukan pengguna yang spesifik.',
    'bannedUser'            => 'Anda tidak dapat masuk karena saat ini Anda diblokir.',
    'logOutBannedUser'      => 'Anda telah keluar karena Anda telah diblokir.',
    'badAttempt'            => 'Anda tidak dapat masuk. Harap periksa kredensial Anda.',
    'noPassword'            => 'Tidak dapat memvalidasi pengguna tanpa kata sandi.',
    'invalidPassword'       => 'Anda tidak dapat masuk. Harap periksa kata sandi Anda.',
    'noToken'               => 'Setiap permintaan harus memiliki token pembawa di header {0}.',
    'badToken'              => 'Akses token tidak sah.',
    'oldToken'              => 'Akses token sudah tidak berlaku.',
    'noUserEntity'          => 'Entitas Pengguna harus disediakan untuk validasi kata sandi.',
    'invalidEmail'          => 'Tidak dapat memverifikasi alamat email yang cocok dengan email yang tercatat.',
    'unableSendEmailToUser' => 'Maaf, ada masalah saat mengirim email. Kami tidak dapat mengirim email ke "{0}".',
    'throttled'             => 'Terlalu banyak permintaan yang dibuat dari alamat IP ini. Anda dapat mencoba lagi dalam {0} detik.',
    'notEnoughPrivilege'    => 'Anda tidak memiliki izin yang diperlukan untuk melakukan operasi yang diinginkan.',
    // JWT Exceptions
    'invalidJWT'     => 'Token tidak valid.',
    'expiredJWT'     => 'Token telah kedaluwarsa.',
    'beforeValidJWT' => 'Token belum tersedia.',

    'email'           => 'Alamat Email',
    'username'        => 'Nama Pengguna',
    'password'        => 'Kata Sandi',
    'passwordConfirm' => 'Kata Sandi (lagi)',
    'haveAccount'     => 'Sudah punya akun?',
    'token'           => 'Token',

    // Buttons
    'confirm' => 'Konfirmasi',
    'send'    => 'Kirim',

    // Registration
    'register'         => 'Registrasi',
    'registerDisabled' => 'Registrasi saat ini tidak diperbolehkan.',
    'registerSuccess'  => 'Selamat bergabung!',

    // Login
    'login'              => 'Masuk',
    'needAccount'        => 'Butuh Akun?',
    'rememberMe'         => 'Ingat saya?',
    'forgotPassword'     => 'Lupa kata sandi?',
    'useMagicLink'       => 'Gunakan tautan masuk',
    'magicLinkSubject'   => 'Tautan masuk Anda',
    'magicTokenNotFound' => 'Tidak dapat memverifikasi tautan.',
    'magicLinkExpired'   => 'Maaf, tautan sudah tidak berlaku.',
    'checkYourEmail'     => 'Periksa email Anda!',
    'magicLinkDetails'   => 'Kami baru saja mengirimi Anda email dengan tautan Masuk di dalamnya. Ini hanya berlaku selama {0} menit.',
    'magicLinkDisabled'  => 'Penggunaan MagicLink saat ini tidak diperbolehkan.',
    'successLogout'      => 'Anda telah berhasil keluar.',
    'backToLogin'        => 'Kembali ke masuk',

    // Passwords
    'errorPasswordLength'       => 'Kata sandi harus setidaknya terdiri dari {0, number} karakter.',
    'suggestPasswordLength'     => 'Kata sandi dapat dibuat mencapai 255 karakter, Disarankan buat kata sandi yang aman dan mudah diingat.',
    'errorPasswordCommon'       => 'Kata sandi tidak boleh menggunakan sandi yang sudah umum.',
    'suggestPasswordCommon'     => 'Kata sandi yang digunakan lebih dari 65 ribu kali pada umumnya dan mudah diretas orang lain.',
    'errorPasswordPersonal'     => 'Kata sandi tidak boleh berisi informasi pribadi.',
    'suggestPasswordPersonal'   => 'Variasi pada alamat email atau nama pengguna Anda tidak boleh digunakan untuk kata sandi.',
    'errorPasswordTooSimilar'   => 'Kata sandi mirip dengan nama pengguna.',
    'suggestPasswordTooSimilar' => 'Jangan gunakan bagian dari nama pengguna Anda dalam kata sandi Anda.',
    'errorPasswordPwned'        => 'Kata sandi {0} telah bocor karena pelanggaran data dan telah dilihat {1, number} kali dalam {2} sandi yang disusupi.',
    'suggestPasswordPwned'      => '{0} tidak boleh digunakan sebagai kata sandi. Jika Anda menggunakannya di mana saja, segera ubah.',
    'errorPasswordEmpty'        => 'Kata sandi wajib diisi.',
    'errorPasswordTooLongBytes' => 'Panjang kata sandi tidak boleh lebih dari {param} byte.',
    'passwordChangeSuccess'     => 'Kata sandi berhasil diubah',
    'userDoesNotExist'          => 'Kata sandi tidak diubah. User tidak ditemukan',
    'resetTokenExpired'         => 'Maaf, token setel ulang Anda sudah kedaluwarsa.',

    // Email Globals
    'emailInfo'      => 'Beberapa informasi tentang seseorang:',
    'emailIpAddress' => 'Alamat IP:',
    'emailDevice'    => 'Perangkat:',
    'emailDate'      => 'Tanggal:',

    // 2FA
    'email2FATitle'       => 'Otentikasi Dua Faktor',
    'confirmEmailAddress' => 'Konfirmasi alamat email Anda.',
    'emailEnterCode'      => 'Konfirmasi email Anda',
    'emailConfirmCode'    => 'Masukkan kode 6 digit yang baru saja kami kirimkan ke alamat email Anda.',
    'email2FASubject'     => 'Kode otentikasi Anda',
    'email2FAMailBody'    => 'Kode otentikasi Anda adalah:',
    'invalid2FAToken'     => 'Kode tidak sesuai.',
    'need2FA'             => 'Anda harus menyelesaikan verifikasi otentikasi dua faktor.',
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
    'needVerification'       => 'Periksa email Anda untuk menyelesaikan verifikasi akun.',

    // Activate
    'emailActivateTitle'    => 'Aktivasi Email',
    'emailActivateBody'     => 'Kami baru saja mengirim email kepada Anda dengan kode untuk mengonfirmasi alamat email Anda. Salin kode itu dan tempel di bawah ini.',
    'emailActivateSubject'  => 'Kode aktivasi Anda',
    'emailActivateMailBody' => 'Silahkan gunakan kode dibawah ini untuk mengaktivasi akun Anda.',
    'invalidActivateToken'  => 'Kode tidak sesuai.',
    'needActivate'          => 'Anda harus menyelesaikan registrasi Anda dengan mengonfirmasi kode yang dikirim ke alamat email Anda.',
    'activationBlocked'     => 'Anda harus mengaktifkan akun Anda sebelum masuk.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} bukan grup yang sah.',
    'missingTitle' => 'Grup-grup diharuskan mempunyai judul.',

    // Permissions
    'unknownPermission' => '{0} bukan izin yang sah.',

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

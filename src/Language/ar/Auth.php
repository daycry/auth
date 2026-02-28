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
    'unknownAuthenticator'  => '{0} ليس توثيق صحيح.',
    'unknownUserProvider'   => 'تعذر تحديد موفر المستخدم الذي يجب استخدامه.',
    'invalidUser'           => 'تعذر تحديد المستخدم المدخل.',
    'bannedUser'            => 'لا يمكن تسجيل الدخول حيث أن حسابك موقوف حالياً.',
    'logOutBannedUser'      => 'لقد تم تسجيل خروجك وذلك لانه تم حظرك.',
    'badAttempt'            => 'لا يمكن تسجيل دخولك. يُرجى التحقق من صحة البيانات الخاصة بك.',
    'noPassword'            => 'لا يمكن التحقق من هوية المستخدم بدون كلمة مرور.',
    'invalidPassword'       => 'تعذر تسجيل الدخول. يرجى التحقق من كلمة المرور الخاصة بك.',
    'noToken'               => 'يجب أن يحتوي كل طلب على رمز حامل (token) في الهيدر {0}.',
    'badToken'              => 'رمز الوصول (Token) غير صالح.',
    'oldToken'              => 'انتهت صلاحية رمز الوصول.',
    'noUserEntity'          => 'يجب توفير كيان المستخدم للتحقق من صحة كلمة المرور.',
    'invalidEmail'          => 'تعذر التحقق من تطابق عنوان البريد الإلكتروني مع البريد الإلكتروني المسجل.',
    'unableSendEmailToUser' => 'عذرا ، كانت هناك مشكلة في إرسال البريد الإلكتروني. لم نتمكن من إرسال بريد إلكتروني إلى "{0}".',
    'throttled'             => 'تم إجراء العديد من الطلبات من عنوان IP هذا. يمكنك المحاولة مرة أخرى في غضون {0} ثانية.',
    'notEnoughPrivilege'    => 'ليس لديك الإذن اللازم لإجراء العملية المطلوبة.',
    // JWT Exceptions
    'invalidJWT'     => 'الرمز غير صالح.',
    'expiredJWT'     => 'انتهت صلاحية الرمز.',
    'beforeValidJWT' => 'الرمز غير متوفر بعد.',

    'email'           => 'عنوان البريد الالكتروني',
    'username'        => 'اسم المستخدم',
    'password'        => 'كلمة المرور',
    'passwordConfirm' => 'كلمة المرور (مرة اخرى)',
    'haveAccount'     => 'هل لديك حساب بالفعل؟',
    'token'           => 'رمز الوصول',

    // Buttons
    'confirm' => 'تاكيد',
    'send'    => 'ارسال',

    // Registration
    'register'         => 'تسجيل حساب',
    'registerDisabled' => 'تسجيل حساب جديد غير مسموح الان.',
    'registerSuccess'  => 'أهلا بك!',

    // Login
    'login'              => 'تسجيل دخول',
    'needAccount'        => 'هل تحتاج الى حساب؟',
    'rememberMe'         => 'تذكر دخولي؟',
    'forgotPassword'     => 'نسيت كلمة المرور؟',
    'useMagicLink'       => 'تسجيل دخول بواسطة رابط دخول',
    'magicLinkSubject'   => 'رابط الدخول الخاص بك',
    'magicTokenNotFound' => 'تعذر التحقق من صحة الرابط.',
    'magicLinkExpired'   => 'عذرا ، لقد انتهت صلاحية الرابط.',
    'checkYourEmail'     => 'تحقق من بريدك الالكتروني!',
    'magicLinkDetails'   => 'لقد أرسلنا لك بريدًا إلكترونيًا يحتوي على رابط تسجيل الدخول بالداخل. الرابط صالح فقط لمدة {0} دقيقة.',
    'magicLinkDisabled'  => 'استخدام الرابط للدخول MagicLink غير مسموح به حاليًا.',
    'successLogout'      => 'لقد قمت بتسجيل الخروج بنجاح.',
    'backToLogin'        => 'العودة إلى نموذج تسجيل الدخول',

    // Passwords
    'errorPasswordLength'       => 'يجب أن تتكون كلمات المرور من {0, number} من الأحرف على الأقل.',
    'suggestPasswordLength'     => 'عبارات المرور - التي يصل طولها إلى 255 حرفًا - تجعل كلمات المرور أكثر أمانًا ويسهل تذكرها.',
    'errorPasswordCommon'       => 'يجب ألا تكون كلمة المرور كلمة مرور شائعة.',
    'suggestPasswordCommon'     => 'تم فحص كلمة المرور مقابل أكثر من 65 ألف كلمة مرور أو كلمات مرور شائعة الاستخدام تم تسريبها من خلال الاختراقات.',
    'errorPasswordPersonal'     => 'لا يمكن أن تحتوي كلمات المرور على معلومات شخصية تم إعادة تجزئتها (re-hashed).',
    'suggestPasswordPersonal'   => 'لا يجب اجزاء من عنوان بريدك الإلكتروني أو اسم المستخدم ككلمات مرور.',
    'errorPasswordTooSimilar'   => 'كلمة المرور مشابهة جدًا لاسم المستخدم.',
    'suggestPasswordTooSimilar' => 'لا تستخدم أجزاء من اسم المستخدم الخاص بك في كلمة المرور الخاصة بك.',
    'errorPasswordPwned'        => 'تم الكشف عن كلمة المرور {0} بسبب اختراق البيانات وشوهدت {1, number} مرة في {2} في كلمات المرور المخترقة.',
    'suggestPasswordPwned'      => 'يجب عدم استخدام {0} أبدًا ككلمة مرور. إذا كنت تستخدمها في أي مكان ، فقم بتغييرها على الفور.',
    'errorPasswordEmpty'        => 'كلمة مرور مطلوبة',
    'errorPasswordTooLongBytes' => 'لا يمكن أن يتجاوز طول كلمة المرور {param} بايت.',
    'passwordChangeSuccess'     => 'تم تغيير كلمة المرور بنجاح',
    'userDoesNotExist'          => 'لم يتم تغيير كلمة المرور. المستخدم غير موجود',
    'resetTokenExpired'         => 'آسف. انتهت صلاحية رمز إعادة التعيين الخاص بك.',

    // Email Globals
    'emailInfo'      => 'بعض المعلومات عن الشخص:',
    'emailIpAddress' => 'عنوان IP:',
    'emailDevice'    => 'الجهاز:',
    'emailDate'      => 'التاريخ:',

    // 2FA
    'email2FATitle'       => 'التحقق بخطوتين',
    'confirmEmailAddress' => 'أكد عنوان بريدك الألكتروني.',
    'emailEnterCode'      => 'تأكيد بريدك الإلكتروني',
    'emailConfirmCode'    => 'أدخل الرمز المكون من 6 أرقام الذي أرسلناه للتو إلى عنوان بريدك الإلكتروني.',
    'email2FASubject'     => 'رمز المصادقة الخاص بك',
    'email2FAMailBody'    => 'رمز المصادقة الخاص بك هو:',
    'invalid2FAToken'     => 'رمز المصادقة غير صحيح.',
    'need2FA'             => 'يجب عليك إكمال التحقق بخطوتين.',
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
    'needVerification'       => 'تحقق من بريدك الإلكتروني لإكمال تنشيط الحساب.',

    // Activate
    'emailActivateTitle'    => 'تفعيل البريد الإلكتروني',
    'emailActivateBody'     => 'لقد أرسلنا إليك بريدًا إلكترونيًا يحتوي على رمز لتأكيد عنوان بريدك الإلكتروني. انسخ هذا الرمز والصقه أدناه.',
    'emailActivateSubject'  => 'رمز التفعيل الخاص بك',
    'emailActivateMailBody' => 'يرجى استخدام الكود أدناه لتفعيل حسابك والبدء في استخدام الموقع.',
    'invalidActivateToken'  => 'الرمز غير صحيح',
    'needActivate'          => 'يجب عليك إكمال تسجيل حسابك عن طريق تأكيد الرمز المرسل إلى عنوان بريدك الإلكتروني.',
    'activationBlocked'     => 'يجب عليك تفعيل حسابك قبل تسجيل الدخول.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} ليست مجموعة صالحة.',
    'missingTitle' => 'يجب أن يكون للمجموعات عنوان.',

    // Permissions
    'unknownPermission' => '{0} ليس صلاحية صحيحة.',

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

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
    'unknownAuthenticator'  => '{0} nie je platný autentifikátor.',
    'unknownUserProvider'   => 'Nie je možné určiť poskytovateľa používateľa, ktorý sa má použiť.',
    'invalidUser'           => 'Nie je možné nájsť zadaného používateľa.',
    'bannedUser'            => 'Prihlásenie zlyhalo, pretože ste dostali zákaz vstupu (ban).',
    'logOutBannedUser'      => 'Boli ste odhlásení, pretože ste dostali zákaz vstupu (ban).',
    'badAttempt'            => 'Prihlásenie zlyhalo. Skontrolujte svoje prihlasovacie údaje.',
    'noPassword'            => 'Nie je možné overiť používateľa bez hesla.',
    'invalidPassword'       => 'Prihlásenie zlyhalo. Skontrolujte svoje heslo.',
    'noToken'               => 'Každá požiadavka musí mať v hlavičke {0} nosný token',
    'badToken'              => 'Prístupový token je neplatný.',
    'oldToken'              => 'Platnosť prístupového tokenu vypršala.',
    'noUserEntity'          => 'Na overenie hesla je potrebné zadať entitu používateľa.',
    'invalidEmail'          => 'Nie je možné overiť, či sa e-mailová adresa zhoduje so zaznamenaným e-mailom.',
    'unableSendEmailToUser' => 'Ľutujeme, pri odosielaní e-mailu sa vyskytol problém. Nepodarilo sa nám odoslať e-mail na adresu „{0}".',
    'throttled'             => 'Z tejto adresy IP bolo odoslaných príliš veľa žiadostí. Môžete to skúsiť znova o {0} sekúnd.',
    'notEnoughPrivilege'    => 'Nemáte potrebné povolenie na vykonanie požadovanej operácie.',
    // JWT Exceptions
    'invalidJWT'     => 'Neplatný token.',
    'expiredJWT'     => 'Platnosť tokenu vypršala.',
    'beforeValidJWT' => 'Token ešte nie je dostupný.',

    'email'           => 'Emailová adresa',
    'username'        => 'Používateľské meno',
    'password'        => 'Heslo',
    'passwordConfirm' => 'Heslo (znova)',
    'haveAccount'     => 'Máte už účet?',
    'token'           => '(To be translated) Token',

    // Buttons
    'confirm' => 'Potvrdiť',
    'send'    => 'Odoslať',

    // Registration
    'register'         => 'Registrácia',
    'registerDisabled' => 'Registrácia momentálne nie je povolená.',
    'registerSuccess'  => 'Vitajte na palube!',

    // Login
    'login'              => 'Prihlásenie',
    'needAccount'        => 'Potrebujete účet?',
    'rememberMe'         => 'Zapamätať si ma?',
    'forgotPassword'     => 'Zabudli ste heslo?',
    'useMagicLink'       => 'Použiť odkaz na prihlásenie',
    'magicLinkSubject'   => 'Váš odkaz na prihlásenie',
    'magicTokenNotFound' => 'Odkaz sa nepodarilo overiť.',
    'magicLinkExpired'   => 'Ľutujeme, platnosť odkazu vypršala.',
    'checkYourEmail'     => 'Skontrolujte e-mail',
    'magicLinkDetails'   => 'Práve sme vám poslali e-mail s odkazom na prihlásenie. Platí iba {0} minút.',
    'magicLinkDisabled'  => 'Použitie magického linku momentálne nie je povolené.',
    'successLogout'      => 'Úspešne ste sa odhlásili.',
    'backToLogin'        => 'Späť na prihlásenie',

    // Passwords
    'errorPasswordLength'       => 'Heslá musia mať aspoň {0, number} znakov.',
    'suggestPasswordLength'     => 'Heslové frázy – až 255 znakov – vytvárajú bezpečnejšie heslá, ktoré sa dajú ľahko zapamätať.',
    'errorPasswordCommon'       => 'Heslo nesmie byť bežné heslo.',
    'suggestPasswordCommon'     => 'Heslo bolo skontrolované oproti viac ako 65 tisícom bežne používaných hesiel alebo hesiel, ktoré unikli prostredníctvom hackerov.',
    'errorPasswordPersonal'     => 'Heslá nemôžu obsahovať opätovne hašované osobné údaje.',
    'suggestPasswordPersonal'   => 'Variácie vašej e-mailovej adresy alebo používateľského mena by sa nemali používať ako heslá.',
    'errorPasswordTooSimilar'   => 'Heslo je príliš podobné používateľskému menu.',
    'suggestPasswordTooSimilar' => 'Vo svojom hesle nepoužívajte časti svojho používateľského mena.',
    'errorPasswordPwned'        => 'Heslo {0} bolo odhalené z dôvodu porušenia ochrany údajov a bolo videné {1, number}-krát z {2} prelomených hesiel.',
    'suggestPasswordPwned'      => '{0} by sa nikdy nemalo používať ako heslo. Ak ho niekde používate, okamžite ho zmeňte.',
    'errorPasswordEmpty'        => 'Vyžaduje sa heslo.',
    'errorPasswordTooLongBytes' => 'Heslo nemôže presiahnuť dĺžku {param} bajtov',
    'passwordChangeSuccess'     => 'Heslo bolo úspešne zmenené',
    'userDoesNotExist'          => 'Heslo nebolo zmenené. Používateľ neexistuje',
    'resetTokenExpired'         => 'Prepáčte. Platnosť vášho resetovacieho tokenu vypršala.',

    // Email Globals
    'emailInfo'      => 'Niektoré informácie o osobe:',
    'emailIpAddress' => 'IP Adresa:',
    'emailDevice'    => 'Zariadenie:',
    'emailDate'      => 'Dátum:',

    // 2FA
    'email2FATitle'       => 'Dvojfaktorová autentifikácia',
    'confirmEmailAddress' => 'Potvrďte svoju e-mailovú adresu.',
    'emailEnterCode'      => 'Potvrďte svoj e-mail',
    'emailConfirmCode'    => 'Zadajte 6-miestny kód, ktorý sme vám práve poslali na vašu e-mailovú adresu.',
    'email2FASubject'     => 'Váš overovací kód',
    'email2FAMailBody'    => 'Váš overovací kód je:',
    'invalid2FAToken'     => 'Kód bol nesprávny.',
    'need2FA'             => 'Musíte vykonať dvojfaktorové overenie.',
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
    'needVerification'       => 'Ak chcete dokončiť aktiváciu účtu, skontrolujte svoj e-mail.',

    // Activate
    'emailActivateTitle'    => 'E-mailová aktivácia',
    'emailActivateBody'     => 'Práve sme vám poslali e-mail s kódom na potvrdenie vašej e-mailovej adresy. Skopírujte tento kód a vložte ho nižšie.',
    'emailActivateSubject'  => 'Váš aktivačný kód',
    'emailActivateMailBody' => 'Pomocou nižšie uvedeného kódu aktivujte svoj účet a môžete začať používať stránku.',
    'invalidActivateToken'  => 'Kód bol nesprávny',
    'needActivate'          => 'Registráciu musíte dokončiť potvrdením kódu zaslaného na vašu e-mailovú adresu.',
    'activationBlocked'     => 'Pred prihlásením si musíte aktivovať svoj účet.',

    // OAuth
    'unknownOauthProvider' => '{0} is not a configured OAuth provider.',
    'invalidOauthState'    => 'Invalid OAuth state. Please try again.',
    'emailNotFoundInOauth' => 'No email address was returned by the OAuth provider.',

    // Groups
    'unknownGroup' => '{0} nie je platná skupina.',
    'missingTitle' => 'Skupiny musia mať názov.',

    // Permissions
    'unknownPermission' => '{0} nie je platným povolením.',
];

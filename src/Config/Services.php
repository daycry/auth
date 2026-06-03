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

namespace Daycry\Auth\Config;

use CodeIgniter\Config\BaseService;
use Daycry\Auth\Auth;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Authorization\Gate;
use Daycry\Auth\Authorization\GroupPermissionRepository;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Libraries\Logger;
use Daycry\Auth\Models\AccessTokenRepository;
use Daycry\Auth\Models\JwtTokenRepository;
use Daycry\Auth\Models\OAuthTokenRepository;
use Daycry\Auth\Models\UserIdentityModel;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

class Services extends BaseService
{
    /**
     * The base auth class
     */
    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return self::getSharedInstance('auth');
        }

        /** @var AuthConfig $config */
        $config = config('Auth');

        return new Auth($config);
    }

    /**
     * Password utilities.
     */
    public static function passwords(bool $getShared = true): Passwords
    {
        if ($getShared) {
            return self::getSharedInstance('passwords');
        }

        /** @var AuthSecurity $config */
        $config = config('AuthSecurity');

        return new Passwords($config);
    }

    /**
     * The authorization gate. Resolves closure rules + class-based policies.
     */
    public static function gate(bool $getShared = true): Gate
    {
        if ($getShared) {
            return self::getSharedInstance('gate');
        }

        return new Gate();
    }

    /**
     * Access-token CRUD repository. Override this binding to swap token storage.
     */
    public static function accessTokenRepository(bool $getShared = true): AccessTokenRepository
    {
        if ($getShared) {
            return self::getSharedInstance('accessTokenRepository');
        }

        return new AccessTokenRepository(model(UserIdentityModel::class));
    }

    /**
     * JWT refresh-token CRUD repository. Override this binding to swap storage.
     */
    public static function jwtTokenRepository(bool $getShared = true): JwtTokenRepository
    {
        if ($getShared) {
            return self::getSharedInstance('jwtTokenRepository');
        }

        return new JwtTokenRepository(model(UserIdentityModel::class));
    }

    /**
     * OAuth identity CRUD repository. Override this binding to swap storage.
     */
    public static function oauthTokenRepository(bool $getShared = true): OAuthTokenRepository
    {
        if ($getShared) {
            return self::getSharedInstance('oauthTokenRepository');
        }

        return new OAuthTokenRepository(model(UserIdentityModel::class));
    }

    /**
     * Transactional persistence of user group/permission pivot rows. Override
     * this binding to change how RBAC assignments are stored.
     */
    public static function groupPermissionRepository(bool $getShared = true): GroupPermissionRepository
    {
        if ($getShared) {
            return self::getSharedInstance('groupPermissionRepository');
        }

        return new GroupPermissionRepository();
    }

    /**
     * The restful log class
     */
    public static function log(bool $getShared = true): Logger
    {
        if ($getShared) {
            return self::getSharedInstance('log');
        }

        helper('checkEndpoint');

        return new Logger(checkEndpoint());
    }

    /**
     * Symfony serializer configured for web-auth/webauthn-lib (options,
     * CredentialRecord and PublicKeyCredential (de)serialization).
     */
    public static function webAuthnSerializer(bool $getShared = true): SerializerInterface
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnSerializer');
        }

        $attestationSupport = AttestationStatementSupportManager::create();
        $attestationSupport->add(NoneAttestationStatementSupport::create());

        return (new WebauthnSerializerFactory($attestationSupport))->create();
    }

    /**
     * Validator for registration (attestation) ceremonies.
     */
    public static function webAuthnAttestationValidator(bool $getShared = true): AuthenticatorAttestationResponseValidator
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnAttestationValidator');
        }

        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins(self::webAuthnAllowedOrigins());

        return AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
    }

    /**
     * Validator for login (assertion) ceremonies.
     */
    public static function webAuthnAssertionValidator(bool $getShared = true): AuthenticatorAssertionResponseValidator
    {
        if ($getShared) {
            return self::getSharedInstance('webAuthnAssertionValidator');
        }

        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins(self::webAuthnAllowedOrigins());

        return AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }

    /**
     * Returns the list of origins accepted during a ceremony.
     *
     * @return list<string>
     */
    private static function webAuthnAllowedOrigins(): array
    {
        $origins = (array) (setting('AuthSecurity.webauthnAllowedOrigins') ?? []);
        if ($origins === []) {
            $origins = [rtrim(base_url(), '/')];
        }

        return array_values(array_map('strval', $origins));
    }
}

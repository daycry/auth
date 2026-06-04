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

namespace Tests;

use BadMethodCallException;
use Daycry\Auth\Auth;
use Daycry\Auth\Authentication\Authentication;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Models\UserModel;
use ReflectionClass;
use Tests\Support\DatabaseTestCase;

/**
 * Covers the Auth facade's magic __call dispatch and guards the @method
 * docblock against drift.
 *
 * @internal
 */
final class AuthTest extends DatabaseTestCase
{
    /**
     * Methods required by AuthenticatorInterface — must exist on every
     * authenticator and therefore be safe to call through the facade.
     */
    private const INTERFACE_METHODS = [
        'attempt',
        'check',
        'getLogCredentials',
        'getUser',
        'loggedIn',
        'login',
        'loginById',
        'logout',
        'recordActiveDate',
    ];

    private function authentication(): Authentication
    {
        $config = new AuthConfig();
        $auth   = new Authentication($config);
        $auth->setProvider(model(UserModel::class));

        return $auth;
    }

    public function testFacadeThrowsOnUnknownMethod(): void
    {
        $this->expectException(BadMethodCallException::class);

        $auth   = new Auth(config('Auth'));
        $method = 'thisMethodDefinitelyDoesNotExist';
        $auth->{$method}();
    }

    public function testInterfaceMethodsExistOnEveryAuthenticator(): void
    {
        foreach (['session', 'access_token', 'jwt'] as $name) {
            $authenticator = $this->authentication()->factory($name);

            foreach (self::INTERFACE_METHODS as $method) {
                $this->assertTrue(
                    method_exists($authenticator, $method),
                    sprintf('%s() must exist on the "%s" authenticator', $method, $name),
                );
            }
        }
    }

    public function testEveryDocumentedFacadeMethodExistsOnSessionAuthenticator(): void
    {
        $ref = new ReflectionClass(Auth::class);
        preg_match_all('/@method\s+\S+\s+(\w+)\(/', (string) $ref->getDocComment(), $matches);
        $documented = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($documented, 'Auth must document its facade methods via @method tags.');

        $session = $this->authentication()->factory('session');

        foreach ($documented as $method) {
            $this->assertTrue(
                method_exists($session, $method),
                sprintf('Documented @method %s() does not exist on the Session authenticator (docblock drift).', $method),
            );
        }
    }
}

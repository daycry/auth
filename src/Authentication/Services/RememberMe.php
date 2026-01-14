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

namespace Daycry\Auth\Authentication\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\I18n\Time;
use Config\Services;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\RememberModel;
use stdClass;

class RememberMe
{
    public function __construct(
        protected RememberModel $rememberModel,
    ) {
    }

    /**
     * Generates a timing-attack safe remember-me token
     * and stores the necessary info in the db and a cookie.
     *
     * @see https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
     */
    public function rememberUser(User $user): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        $expires   = $this->calcExpires();

        $rawToken = $selector . ':' . $validator;

        // Store it in the database.
        $this->rememberModel->rememberUser(
            $user,
            $selector,
            $this->hashValidator($validator),
            $expires,
        );

        $this->setRememberMeCookie($rawToken);
    }

    public function check(): ?stdClass
    {
        // Get remember-me token.
        $remember = $this->getRememberMeToken();
        if ($remember === null) {
            return null;
        }

        // Check the remember-me token.
        $token = $this->checkRememberMeToken($remember);
        if ($token === false) {
            return null;
        }

        return $token;
    }

    public function refresh(stdClass $token): void
    {
        // Update validator.
        $validator = bin2hex(random_bytes(20));

        $token->hashedValidator = $this->hashValidator($validator);
        $token->expires         = $this->calcExpires();

        $this->rememberModel->updateRememberValidator($token);

        $rawToken = $token->selector . ':' . $validator;

        $this->setRememberMeCookie($rawToken);
    }

    public function removeKey(): void
    {
        /** @var Response $response */
        $response = Services::response();

        // Remove remember-me cookie
        $response->deleteCookie(
            service('settings')->get('Auth.sessionConfig')['rememberCookieName'],
            service('settings')->get('Cookie.domain'),
            service('settings')->get('Cookie.path'),
            service('settings')->get('Cookie.prefix'),
        );
    }

    public function purgeOldTokens(): void
    {
        $this->rememberModel->purgeOldRememberTokens();
    }

    public function purge(User $user): void
    {
        $this->rememberModel->purgeRememberTokens($user);
    }

    public function getRememberMeToken(): ?string
    {
        /** @var IncomingRequest $request */
        $request = Services::request();

        $cookieName = service('settings')->get('Cookie.prefix') . service('settings')->get('Auth.sessionConfig')['rememberCookieName'];

        return $request->getCookie($cookieName);
    }

    protected function setRememberMeCookie(string $rawToken): void
    {
        /** @var Response $response */
        $response = Services::response();

        // Save it to the user's browser in a cookie.
        // Create the cookie
        $response->setCookie(
            service('settings')->get('Auth.sessionConfig')['rememberCookieName'],
            $rawToken,                                             // Value
            service('settings')->get('Auth.sessionConfig')['rememberLength'],      // # Seconds until it expires
            service('settings')->get('Cookie.domain'),
            service('settings')->get('Cookie.path'),
            service('settings')->get('Cookie.prefix'),
            service('settings')->get('Cookie.secure'),                          // Only send over HTTPS?
            true,                                                  // Hide from Javascript?
        );
    }

    protected function calcExpires(): string
    {
        $timestamp = Time::now()->getTimestamp() + service('settings')->get('Auth.sessionConfig')['rememberLength'];

        return Time::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
    }

    /**
     * Hash remember-me validator
     */
    protected function hashValidator(string $validator): string
    {
        return hash('sha256', $validator);
    }

    /**
     * @return false|stdClass
     */
    protected function checkRememberMeToken(string $remember)
    {
        if (! str_contains($remember, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $remember);

        $hashedValidator = hash('sha256', $validator);

        $token = $this->rememberModel->getRememberToken($selector);

        if ($token === null) {
            return false;
        }

        if (hash_equals($token->hashedValidator, $hashedValidator) === false) {
            return false;
        }

        return $token;
    }
}

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

namespace Daycry\Auth\Controllers;

use Daycry\Auth\Config\AuthOAuth;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Libraries\Oauth\OauthManager;
use Throwable;

class OauthController extends BaseAuthController
{
    protected $helpers = ['setting', 'url'];

    /**
     * Get rules for validation
     */
    protected function getValidationRules(): array
    {
        return [];
    }

    public function redirect(string $provider)
    {
        return $this->createManager()->setProvider($provider)->redirect();
    }

    /**
     * Begins linking an OAuth provider to the *currently authenticated* user.
     *
     * Unlike redirect()/callback() (which sign a user in), this stashes the
     * logged-in user's id so the shared callback links the provider to them
     * explicitly — the safe alternative to merging accounts by e-mail.
     */
    public function link(string $provider)
    {
        if (! auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginPage());
        }

        session()->set('oauth_link_user_id', auth()->id());

        return $this->createManager()->setProvider($provider)->redirect();
    }

    public function callback(string $provider)
    {
        try {
            $this->createManager()->setProvider($provider)->handleCallback(
                $this->request->getGet('code') ?? '',
                $this->request->getGet('state') ?? '',
                $this->request->getGet('iss'),
            );

            return redirect()->to(config('Auth')->loginRedirect());
        } catch (AuthenticationException $e) {
            // User-facing, translated messages (invalid state, unverified e-mail, ...).
            return redirect()->to(config('Auth')->loginPage())->with('error', $e->getMessage());
        } catch (Throwable $e) {
            // Anything else (database, provider HTTP, misconfiguration) may carry
            // internal details: log it and show a generic message instead.
            log_message('error', 'OAuth callback for provider {provider} failed: {message}', [
                'provider' => $provider,
                'message'  => $e->getMessage(),
            ]);

            return redirect()->to(config('Auth')->loginPage())->with('error', lang('Auth.badAttempt'));
        }
    }

    /**
     * Builds the OAuth manager for the configured providers.
     */
    protected function createManager(): OauthManager
    {
        /** @var AuthOAuth $config */
        $config = config('AuthOAuth');

        return new OauthManager($config);
    }
}

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

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Libraries\TokenEmailSender;
use Daycry\Auth\Libraries\Utils;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Models\UserModel;

/**
 * Handles "Magic Link" logins - an email-based
 * no-password login protocol. This works much
 * like password reset would, but Shield provides
 * this in place of password reset. It can also
 * be used on it's own without an email/password
 * login strategy.
 */
class MagicLinkController extends BaseAuthController
{
    /**
     * @var UserModel
     */
    protected $provider;

    public function __construct()
    {
        /** @var class-string<UserModel> $providerClass */
        $providerClass = setting('Auth.userProvider');

        $this->provider = new $providerClass();
    }

    /**
     * Displays the view to enter their email address
     * so an email can be sent to them.
     */
    public function loginView(): ResponseInterface
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins')) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        if (($redirect = $this->redirectIfLoggedIn()) instanceof RedirectResponse) {
            return $redirect;
        }

        $content = $this->view(setting('Auth.views')['magic-link-login']);

        return $this->response->setBody($content);
    }

    /**
     * Receives the email from the user, creates the hash
     * to a user identity, and sends an email to the given
     * email address.
     */
    public function loginAction(): RedirectResponse
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins')) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError('magic-link');
        }

        $delivery = $this->request->getPost('delivery') === 'code' ? 'code' : 'link';

        if ($delivery === 'link' && ! setting('AuthSecurity.magicLinkEnableLink')) {
            return $this->handleError('magic-link', lang('Auth.magicLinkDisabled'));
        }
        if ($delivery === 'code' && ! setting('AuthSecurity.magicLinkEnableCode')) {
            return $this->handleError('magic-link', lang('Auth.magicLinkDisabled'));
        }

        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($delivery === 'code') {
            // Session-bound: remember the requested email regardless of whether
            // it exists (anti-enumeration), then show the code form.
            session()->set('magicCodeEmail', $email);

            if ($user !== null) {
                try {
                    (new TokenEmailSender())->sendTokenEmail(
                        $user,
                        Session::ID_TYPE_MAGIC_CODE,
                        setting('AuthSecurity.magicCodeLifetime'),
                        lang('Auth.magicCodeSubject'),
                        setting('Auth.views')['magic-link-code-email'],
                        [],
                        static fn (): string => Utils::generateNumericCode(6),
                    );
                } catch (RuntimeException $e) {
                    // Swallow send failures so the response can't be used to
                    // distinguish existing from non-existing accounts.
                    log_message('error', 'Magic code email failed: {m}', ['m' => $e->getMessage()]);
                }
            }

            return redirect()->route('magic-link-code');
        }

        // Link mode (existing behaviour, now anti-enumeration: unknown emails
        // and send failures both fall through to the generic message page).
        if ($user !== null) {
            try {
                (new TokenEmailSender())->sendTokenEmail(
                    $user,
                    Session::ID_TYPE_MAGIC_LINK,
                    setting('AuthSecurity.magicLinkLifetime'),
                    lang('Auth.magicLinkSubject'),
                    setting('Auth.views')['magic-link-email'],
                );
            } catch (RuntimeException $e) {
                log_message('error', 'Magic link email failed: {m}', ['m' => $e->getMessage()]);
            }
        }

        return redirect()->route('magic-link-message');
    }

    /**
     * Display the "What's happening/next" message to the user.
     */
    protected function displayMessage(): string
    {
        return $this->view(setting('Auth.views')['magic-link-message']);
    }

    /**
     * Shows the message view (public route)
     */
    public function messageView(): ResponseInterface
    {
        $content = $this->view(setting('Auth.views')['magic-link-message']);

        return $this->response->setBody($content);
    }

    /**
     * Handles the GET request from the email
     */
    public function verify(): RedirectResponse
    {
        if (! setting('AuthSecurity.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $token = $this->request->getGet('token');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        $identifier = $token ?? '';

        // No token found?
        if ($identity === null) {
            $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_LINK, $identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicTokenNotFound'));
        }

        // Delete the db entry so it cannot be used again.
        $identityModel->delete($identity->id);

        // Token expired?
        if (Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_LINK, $identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicLinkExpired'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined
        if ($authenticator->hasAction($identity->user_id)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
        }

        // Log the user in
        $authenticator->loginById($identity->user_id);

        $user = $authenticator->getUser();

        $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_LINK, $identifier, true, $user->id);

        // Give the developer a way to know the user
        // logged in via a magic link.
        session()->setTempdata('magicLogin', true);

        Events::trigger('magicLogin');

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return         array<string, array<string, list<string>|string>>
     * @phpstan-return array<string, array<string, string|list<string>>>
     */
    protected function getValidationRules(): array
    {
        return [
            'email' => config('Auth')->emailValidationRules,
        ];
    }
}

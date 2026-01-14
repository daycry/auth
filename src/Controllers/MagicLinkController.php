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
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Models\LoginModel;
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
        if (! setting('Auth.allowMagicLinkLogins')) {
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
        if (! setting('Auth.allowMagicLinkLogins')) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        // Validate email format
        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError('magic-link');
        }

        // Check if the user exists
        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($user === null) {
            return $this->handleError('magic-link', lang('Auth.invalidEmail'));
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous magic-link identities
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        // Generate the code and save it as an identity
        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime'))->format('Y-m-d H:i:s'),
        ]);

        /** @var IncomingRequest $request */
        $request = service('request');

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send the user an email with the code
        helper('email');
        $email = emailer()->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($user->email);
        $email->setSubject(lang('Auth.magicLinkSubject'));
        $email->setMessage($this->view(setting('Auth.views')['magic-link-email'], [
            'token'     => $token,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'date'      => $date,
        ]));

        if ($email->send(false) === false) {
            log_message('error', $email->printDebugger(['headers']));

            return $this->handleError('magic-link', lang('Auth.unableSendEmailToUser', [$user->email]));
        }

        // Clear the email
        $email->clear();

        // Redirect to message page instead of returning the view directly
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
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $token = $this->request->getGet('token');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        $identifier = $token ?? '';

        // No token found?
        if ($identity === null) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicTokenNotFound'));
        }

        // Delete the db entry so it cannot be used again.
        $identityModel->delete($identity->id);

        // Token expired?
        if (Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt($identifier, false);

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

        $this->recordLoginAttempt($identifier, true, $user->id);

        // Give the developer a way to know the user
        // logged in via a magic link.
        session()->setTempdata('magicLogin', true);

        Events::trigger('magicLogin');

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * @param int|string|null $userId
     */
    private function recordLoginAttempt(
        string $identifier,
        bool $success,
        $userId = null,
    ): void {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId,
        );
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

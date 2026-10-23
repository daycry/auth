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
use Daycry\Auth\Entities\User;
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
        if ($this->magicLinkDisabled()) {
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
        if ($this->magicLinkDisabled()) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        $rules    = $this->getValidationRules();
        $postData = $this->request->getPost();

        // Resolve the form URL once. The 'magic-link' route is login/magic-link,
        // so redirecting to the literal 'magic-link' path would 404 — use the
        // named route.
        $formUrl = route_to('magic-link');

        if (! $this->validateRequest($postData, $rules)) {
            return $this->handleValidationError($formUrl);
        }

        $delivery = $this->request->getPost('delivery') === 'code' ? 'code' : 'link';

        if ($delivery === 'link' && ! setting('AuthSecurity.magicLinkEnableLink')) {
            return $this->handleError($formUrl, lang('Auth.magicLinkDisabled'));
        }
        if ($delivery === 'code' && ! setting('AuthSecurity.magicLinkEnableCode')) {
            return $this->handleError($formUrl, lang('Auth.magicLinkDisabled'));
        }

        $email = $this->request->getPost('email');
        $user  = $this->provider->findByCredentials(['email' => $email]);

        if ($delivery === 'code') {
            // Session-bound: remember the requested email regardless of whether
            // it exists (anti-enumeration), then show the code form.
            session()->set('magicCodeEmail', $email);

            if ($user !== null) {
                try {
                    service('tokenEmailSender')->sendTokenEmail(
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
                service('tokenEmailSender')->sendTokenEmail(
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
        return $this->response->setBody($this->displayMessage());
    }

    /**
     * Shows the 6-digit code entry form. Only reachable after a code has been
     * requested (the pending email is in the session).
     */
    public function codeView(): ResponseInterface
    {
        if ($this->magicLinkDisabled(true)) {
            return $this->handleError(
                config('Auth')->loginRoute(),
                lang('Auth.magicLinkDisabled'),
            );
        }

        if (! session()->has('magicCodeEmail')) {
            return redirect()->route('magic-link');
        }

        $content = $this->view(setting('Auth.views')['magic-link-code']);

        return $this->response->setBody($content);
    }

    /**
     * Handles the GET request from the email
     */
    public function verify(): RedirectResponse
    {
        if ($this->magicLinkDisabled()) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $token = $this->request->getGet('token');
        // A malformed query (e.g. token[]=x) is just an unknown token.
        $token = is_string($token) ? $token : null;

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        // No token found?
        if ($identity === null) {
            return $this->failLinkVerification($token, lang('Auth.magicTokenNotFound'));
        }

        // Delete the db entry so it cannot be used again.
        $identityModel->delete($identity->id);

        // Token expired?
        if (Time::now()->isAfter($identity->expires)) {
            return $this->failLinkVerification($token, lang('Auth.magicLinkExpired'));
        }

        $user = $this->provider->findById($identity->user_id);

        // The account behind the token no longer exists.
        if ($user === null) {
            return $this->failLinkVerification($token, lang('Auth.magicTokenNotFound'));
        }

        // A banned user must not get a session (password login refuses them
        // in checkLogin(); loginById() does not check).
        if ($user->isBanned()) {
            return $this->failLinkVerification($token, $this->banMessage($user), $user->id);
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined
        if ($authenticator->hasAction($user->id)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
        }

        $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_LINK, $token ?? '', true, $user->id);

        return $this->completeMagicLogin($authenticator, $user);
    }

    /**
     * Verifies the 6-digit code (code delivery mode). The pending email is read
     * from the session, the code is matched against that user's own MAGIC_CODE
     * identity (never a global lookup), and the account's brute-force lockout
     * applies. Generic errors throughout (anti-enumeration).
     */
    public function verifyCode(): RedirectResponse
    {
        if ($this->magicLinkDisabled(true)) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $email = session()->get('magicCodeEmail');
        if (empty($email)) {
            return redirect()->route('magic-link');
        }

        $code = (string) $this->request->getPost('token');
        $user = $this->provider->findByCredentials(['email' => $email]);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($user !== null) {
            $lockout       = $authenticator->getLockoutManager();
            $lockoutResult = $lockout->isLockedOut($user);

            if ($lockoutResult !== null) {
                // Generic message even while locked out: surfacing the lockout
                // reason here would confirm the account exists (a non-existent
                // email is never locked out and always gets the generic error),
                // breaking anti-enumeration.
                return redirect()->route('magic-link-code')->with('error', lang('Auth.magicCodeInvalid'));
            }

            /** @var UserIdentityModel $identityModel */
            $identityModel = model(UserIdentityModel::class);
            $identities    = $identityModel->getIdentitiesByTypes($user, [Session::ID_TYPE_MAGIC_CODE]);
            $identity      = $identities[0] ?? null;

            if (
                $identity !== null
                && $code !== ''
                && hash_equals((string) $identity->secret, hash('sha256', $code))
                && Time::now()->isBefore($identity->expires)
            ) {
                // Success — consume the code (single-use) and clear state.
                $identityModel->delete($identity->id);
                $lockout->resetOnSuccess($user);
                session()->remove('magicCodeEmail');

                if ($user->isBanned()) {
                    $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_CODE, (string) $email, false, $user->id);
                    Events::trigger('failedLogin', ['magicCode' => $code]);

                    return redirect()->route('magic-link')->with('error', $this->banMessage($user));
                }

                $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_CODE, (string) $email, true, $user->id);

                // Respect any pending post-auth action (mirrors verify()).
                if ($authenticator->hasAction($user->id)) {
                    return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
                }

                return $this->completeMagicLogin($authenticator, $user);
            }

            // Existing user, bad/expired code → count the failed attempt.
            $lockout->recordFailedAttempt($user);
        }

        // Generic failure path (unknown email OR bad/expired code).
        $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_CODE, (string) $email, false);
        Events::trigger('failedLogin', ['magicCode' => $code]);

        return redirect()->route('magic-link-code')->with('error', lang('Auth.magicCodeInvalid'));
    }

    /**
     * Whether magic-link logins are off (or, with $code, the code mode).
     */
    protected function magicLinkDisabled(bool $code = false): bool
    {
        return ! setting('AuthSecurity.allowMagicLinkLogins')
            || ($code && ! setting('AuthSecurity.magicLinkEnableCode'));
    }

    /**
     * Records the failed link verification, fires `failedLogin` and sends the
     * user back to the magic-link form with $error.
     */
    protected function failLinkVerification(?string $token, string $error, ?int $userId = null): RedirectResponse
    {
        $this->recordLoginAttempt(Session::ID_TYPE_MAGIC_LINK, $token ?? '', false, $userId);

        Events::trigger('failedLogin', ['magicLinkToken' => $token]);

        return redirect()->route('magic-link')->with('error', $error);
    }

    /**
     * Signs the verified user in and flags the session as a magic login.
     */
    protected function completeMagicLogin(Session $authenticator, User $user): RedirectResponse
    {
        $authenticator->loginById($user->id);

        // Give the developer a way to know the user
        // logged in via a magic link.
        session()->setTempdata('magicLogin', true);

        Events::trigger('magicLogin', $user);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * The message shown to a banned user.
     */
    protected function banMessage(User $user): string
    {
        return $user->getBanMessage() ?? lang('Auth.bannedUser');
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

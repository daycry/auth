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

namespace Daycry\Auth\Authentication\Actions;

use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Interfaces\ActionInterface;
use Daycry\Auth\Models\UserIdentityModel;
use Daycry\Auth\Traits\Viewable;

/**
 * Base class for post-authentication actions (Email2FA, EmailActivator, Totp2FA).
 *
 * Provides shared helpers so concrete actions only implement their
 * action-specific logic.
 */
abstract class AbstractAction implements ActionInterface
{
    use Viewable;

    /**
     * The identity type this action operates on.
     */
    protected string $type;

    /**
     * Returns the pending-login user from the session authenticator.
     *
     * @throws RuntimeException When no pending user exists.
     */
    protected function requirePendingUser(): User
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        return $user;
    }

    /**
     * Returns the session authenticator instance.
     */
    protected function getSessionAuthenticator(): Session
    {
        /** @var Session */
        return auth('session')->getAuthenticator();
    }

    /**
     * Returns the UserIdentityModel instance.
     */
    protected function getIdentityModel(): UserIdentityModel
    {
        /** @var UserIdentityModel */
        return model(UserIdentityModel::class);
    }

    /**
     * Returns an identity for this action type for the given user.
     */
    protected function getIdentity(User $user): ?UserIdentity
    {
        return $this->getIdentityModel()->getIdentityByType($user, $this->type);
    }

    /**
     * Second-factor verification shared by the code-based 2FA actions.
     *
     * Applies the per-user brute-force lockout that guards password login,
     * runs $check, and on success clears the failure counter, removes the
     * pending marker and completes the login. On failure (or while locked
     * out) it re-renders $viewKey with an error flash.
     *
     * @param callable(User): bool        $check      Verifies the submitted factor
     * @param string                      $viewKey    `Auth::$views` key of the form
     * @param string                      $errorKey   Lang key flashed on a wrong factor
     * @param (callable(User): void)|null $afterLogin Runs after completeLogin()
     *
     * @return RedirectResponse|string
     */
    protected function verifySecondFactor(callable $check, string $viewKey, string $errorKey, ?callable $afterLogin = null)
    {
        $authenticator  = $this->getSessionAuthenticator();
        $user           = $this->requirePendingUser();
        $lockoutManager = $authenticator->getLockoutManager();

        // Without the lockout the second factor would be infinitely guessable
        // once the first factor has been passed.
        $lockoutResult = $lockoutManager->isLockedOut($user);

        if ($lockoutResult !== null) {
            session()->setFlashdata('error', $lockoutResult->reason());

            return $this->view(setting('Auth.views')[$viewKey]);
        }

        if (! $check($user)) {
            // Counts like a password failure: the account locks once the
            // configured threshold is reached.
            $lockoutManager->recordFailedAttempt($user);

            session()->setFlashdata('error', lang($errorKey));

            return $this->view(setting('Auth.views')[$viewKey]);
        }

        $lockoutManager->resetOnSuccess($user);

        // Remove the pending marker and complete login
        $this->getIdentityModel()->deleteIdentitiesByType($user, $this->type);

        $authenticator->completeLogin($user);

        if ($afterLogin !== null) {
            $afterLogin($user);
        }

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Inserts the pending-login marker that tells Session this action is
     * required, replacing any stale one.
     *
     * @param string $messageKey Lang key stored as the pending message
     *
     * @return string $secret, the value createIdentity() reports as active
     */
    protected function insertPendingMarker(User $user, string $name, string $secret, string $messageKey): string
    {
        $this->getIdentityModel()->insert([
            'user_id' => $user->id,
            'type'    => $this->type,
            'name'    => $name,
            'secret'  => $secret,
            'extra'   => lang($messageKey),
        ]);

        return $secret;
    }

    /**
     * Sends an action e-mail (2FA code, activation code) rendered from the
     * `Auth::$views` entry $viewKey. The view also receives `user`,
     * `ipAddress`, `userAgent` and `date`.
     *
     * @param array<string, mixed> $data Extra view data
     *
     * @throws RuntimeException When the e-mail cannot be sent.
     */
    protected function sendActionEmail(User $user, string $to, string $subject, string $viewKey, array $data = []): void
    {
        /** @var IncomingRequest $request */
        $request = service('request');

        helper('email');
        $email = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($this->view(
            setting('Auth.views')[$viewKey],
            $data + [
                'user'      => $user,
                'ipAddress' => $request->getIPAddress(),
                'userAgent' => (string) $request->getUserAgent(),
                'date'      => Time::now()->toDateTimeString(),
            ],
            ['debug' => false],
        ));

        if ($email->send(false) === false) {
            throw new RuntimeException('Cannot send email for user: ' . $user->email . "\n" . $email->printDebugger(['headers']));
        }

        // Clear the email
        $email->clear();
    }

    /**
     * Returns the string type of the action class.
     */
    public function getType(): string
    {
        return $this->type;
    }
}

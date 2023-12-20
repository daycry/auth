<?php

declare(strict_types=1);

namespace Daycry\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Interfaces\AuthenticatorInterface;
use Daycry\Auth\Config\Auth;

/**
 * Authentication Filter.
 *
 * JSON Web Token authentication for web applications
 * Access Token authentication for web applications
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('checkEndpoint');

        if (! $request instanceof IncomingRequest) {
            return;
        }

        $endpoint = checkEndpoint();
        
        $alias = ($endpoint) ? $endpoint->auth : service('settings')->get('Auth.defaultAuthenticator');
        $alias = ($arguments) ? $arguments[0] : $alias;

        /** @var  AuthenticatorInterface $authenticator */
        $authenticator = auth($alias)->getAuthenticator();

        /** @var Auth $config */
        $config = config(Auth::class);

        if($authenticator instanceof Session)
        {
            if (auth($alias)->loggedIn()) {
                if (setting('Auth.recordActiveDate')) {
                    $authenticator->recordActiveDate();
                }

                // Block inactive users when Email Activation is enabled
                $user = $authenticator->getUser();

                if ($user->isBanned()) {
                    $error = $user->getBanMessage() ?? lang('Auth.logOutBannedUser');
                    $authenticator->logout();
    
                    return redirect()->to($config->logoutRedirect())
                        ->with('error', $error);
                }

                if ($user !== null && ! $user->isActivated()) {
                    // If an action has been defined for register, start it up.
                    /** @var Session $authenticator */
                    $hasAction = $authenticator->startUpAction('register', $user);
                    if ($hasAction) {
                        return redirect()->route('auth-action-show')
                            ->with('error', lang('Auth.activationBlocked'));
                    }
                }

                return;
            }

            /** @var Session $authenticator */
            if ($authenticator->isPending()) {
                return redirect()->route('auth-action-show')
                    ->with('error', $authenticator->getPendingMessage());
            }
    
            if (uri_string() !== route_to('login')) {
                $session = session();
                $session->setTempdata('beforeLoginUrl', current_url(), 300);
            }
    
            return redirect()->route('login');

        }else{
            $result = $authenticator->attempt();

            if (! $result->isOK()) {
                return service('response')
                    ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                    ->setJson(['message' => $result->reason()]);
            }
    
            if (setting('Auth.recordActiveDate')) {
                $authenticator->recordActiveDate();
            }
        }
    }

    /**
     * We don't have anything to do here.
     *
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required
    }
}
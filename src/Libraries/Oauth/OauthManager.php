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

namespace Daycry\Auth\Libraries\Oauth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Models\UserIdentityModel;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;

class OauthManager
{
    protected AuthConfig $config;
    protected AbstractProvider $provider;
    protected string $providerName;

    public function __construct(AuthConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Set the provider instance directly (testing)
     */
    public function setProviderInstance(AbstractProvider $provider, string $name = 'test'): self
    {
        $this->provider     = $provider;
        $this->providerName = $name;

        return $this;
    }

    public function setProvider(string $providerName): self
    {
        if (! isset($this->config->providers[$providerName])) {
            throw new AuthenticationException(lang('Auth.unknownOauthProvider', [$providerName]));
        }

        $this->providerName = $providerName;
        $config             = $this->config->providers[$providerName];

        if ($providerName === 'azure') {
            if (! empty($config['tenant'])) {
                $config['urlAuthorize']   = 'https://login.microsoftonline.com/' . $config['tenant'] . '/oauth2/v2.0/authorize';
                $config['urlAccessToken'] = 'https://login.microsoftonline.com/' . $config['tenant'] . '/oauth2/v2.0/token';
            }
            $this->provider = new Azure($config);
        } else {
            // Default to GenericProvider or other specific ones
            $this->provider = new GenericProvider($config);
        }

        return $this;
    }

    public function refreshAccessToken(User $user): ?AccessTokenInterface
    {
        if (! isset($this->provider)) {
            throw new AuthenticationException(lang('Auth.unknownOauthProvider', [$this->providerName ?? 'null']));
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $type = 'oauth_' . $this->providerName;

        // Find identity for this user and provider
        /** @var UserIdentity|null $identity */
        $identity = $identityModel->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if (! $identity || empty($identity->extra)) {
            return null;
        }

        try {
            $grant = new RefreshToken();
            $token = $this->provider->getAccessToken($grant, ['refresh_token' => $identity->extra]);

            // Update identity
            $identity->secret2 = $token->getToken();
            if ($token->getRefreshToken()) {
                $identity->extra = $token->getRefreshToken();
            }

            if ($token->getExpires()) {
                $identity->expires = Time::createFromTimestamp($token->getExpires());
            }

            $identityModel->save($identity);

            return $token;
        } catch (IdentityProviderException $e) {
            // Handle error (e.g. refresh token expired or revoked)
            return null;
        }
    }

    public function redirect(): RedirectResponse
    {
        $authorizationUrl = $this->provider->getAuthorizationUrl();
        session()->set('oauth2state', $this->provider->getState());

        return redirect()->to($authorizationUrl);
    }

    public function handleCallback(string $code, string $state): User
    {
        $sessionState = session()->get('oauth2state');

        if ($state === '' || $state === '0' || ($state !== $sessionState)) {
            session()->remove('oauth2state');

            throw new AuthenticationException(lang('Auth.invalidOauthState'));
        }

        session()->remove('oauth2state');

        try {
            // Try to get an access token (using the authorization code grant)
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Optional: Now you have a token you can look up a users profile data
            $userProfile = $this->provider->getResourceOwner($token);

            return $this->processUser($userProfile, $token);
        } catch (IdentityProviderException $e) {
            throw new AuthenticationException($e->getMessage());
        }
    }

    protected function processUser($userProfile, $token): User
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Normalize data based on provider
        $email    = null;
        $name     = null;
        $socialId = $userProfile->getId();

        if ($this->providerName === 'azure') {
            $email = $userProfile->claim('email') ?: $userProfile->claim('unique_name');
            $name  = $userProfile->claim('name');
        } else {
            // Standard OIDC/OAuth claims
            $data  = $userProfile->toArray();
            $email = $data['email'] ?? null;
            $name  = $data['name'] ?? null;
        }

        if (empty($email)) {
            throw new AuthenticationException(lang('Auth.emailNotFoundInOauth'));
        }

        // Check if identity exists
        // We use 'oauth_{provider}' as type
        $type     = 'oauth_' . $this->providerName;
        $identity = $identityModel->where('type', $type)
            ->where('secret', $socialId)
            ->first();

        $user = null;

        if ($identity) {
            /** @var UserIdentity $identity */
            $user = $identity->user();
        } else {
            // Check if user with email exists to link
            $provider = auth()->getProvider();
            $user     = $provider->findByCredentials(['email' => $email]);

            if (! $user instanceof User) {
                // Create new user
                $user = new User([
                    'username' => explode('@', $email)[0] . '_' . bin2hex(random_bytes(3)), // Generate a username
                    'active'   => true,
                ]);
                $provider->save($user);
                $user = $provider->findById($provider->getInsertID());

                // Add to default group
                $provider->addToDefaultGroup($user);
            }

            // Create identity
            $identityModel->insert([
                'user_id' => $user->id,
                'type'    => $type,
                'secret'  => $socialId, // Social ID
                'secret2' => $token->getToken(), // Access Token (optional to store)
                'extra'   => $token->getRefreshToken(), // Refresh Token (optional)
                'expires' => $token->getExpires() ? Time::createFromTimestamp($token->getExpires()) : null,
            ]);
        }

        // Login the user
        auth()->login($user);

        return $user;
    }
}

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
use Daycry\Auth\Config\AuthOAuth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Models\UserIdentityModel;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;

class OauthManager
{
    protected AuthConfig $config;
    protected AbstractProvider $provider;
    protected string $providerName;

    /**
     * Map of provider alias → FQCN for well-known providers.
     * Any provider not listed here falls back to GenericProvider.
     *
     * @var array<string, class-string<AbstractProvider>>
     */
    protected array $providerMap = [
        'azure'    => Azure::class,
        'google'   => Google::class,
        'facebook' => Facebook::class,
        'github'   => Github::class,
    ];

    public function __construct(AuthConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Set the provider instance directly (testing).
     */
    public function setProviderInstance(AbstractProvider $provider, string $name = 'test'): self
    {
        $this->provider     = $provider;
        $this->providerName = $name;

        return $this;
    }

    /**
     * Resolve and instantiate the OAuth provider for the given alias.
     */
    public function setProvider(string $providerName): self
    {
        if (! isset($this->config->providers[$providerName])) {
            throw new AuthenticationException(lang('Auth.unknownOauthProvider', [$providerName]));
        }

        $this->providerName = $providerName;
        $providerConfig     = $this->config->providers[$providerName];

        // Azure needs tenant-specific URLs when a tenant is given
        if ($providerName === 'azure' && ! empty($providerConfig['tenant'])) {
            $tenant                           = $providerConfig['tenant'];
            $providerConfig['urlAuthorize']   = 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize';
            $providerConfig['urlAccessToken'] = 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token';
        }

        $class          = $this->providerMap[$providerName] ?? GenericProvider::class;
        $this->provider = new $class($providerConfig);

        return $this;
    }

    /**
     * Refresh the stored access token for a user via the refresh-token grant.
     */
    public function refreshAccessToken(User $user): ?AccessTokenInterface
    {
        if (! isset($this->provider)) {
            throw new AuthenticationException(lang('Auth.unknownOauthProvider', [$this->providerName ?? 'null']));
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $type = 'oauth_' . $this->providerName;

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

            $identity->secret2 = $token->getToken();

            if ($token->getRefreshToken()) {
                $identity->extra = $token->getRefreshToken();
            }

            if ($token->getExpires()) {
                $identity->expires = Time::createFromTimestamp($token->getExpires());
            }

            $identityModel->save($identity);

            return $token;
        } catch (IdentityProviderException) {
            return null;
        }
    }

    /**
     * Redirect the user to the OAuth provider's authorization page.
     */
    public function redirect(): RedirectResponse
    {
        $authorizationUrl = $this->provider->getAuthorizationUrl();
        session()->set('oauth2state', $this->provider->getState());

        return redirect()->to($authorizationUrl);
    }

    /**
     * Handle the authorization callback from the OAuth provider.
     */
    public function handleCallback(string $code, string $state): User
    {
        $sessionState = session()->get('oauth2state');

        if ($state === '' || $state === '0' || ($state !== $sessionState)) {
            session()->remove('oauth2state');

            throw new AuthenticationException(lang('Auth.invalidOauthState'));
        }

        session()->remove('oauth2state');

        try {
            $token       = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $userProfile = $this->provider->getResourceOwner($token);

            return $this->processUser($userProfile, $token);
        } catch (IdentityProviderException $e) {
            throw new AuthenticationException($e->getMessage());
        }
    }

    /**
     * Extract normalised email, name, and social ID from a resource owner.
     *
     * Each League provider exposes data differently:
     *   - Google / Facebook / GitHub : getEmail(), getName()
     *   - Azure                      : claim('email') / claim('unique_name')
     *   - GenericProvider & others   : toArray() fallback
     *
     * @return array{id: string, email: string|null, name: string|null}
     */
    protected function extractUserData(ResourceOwnerInterface $resourceOwner): array
    {
        $id    = (string) $resourceOwner->getId();
        $email = null;
        $name  = null;

        if ($this->providerName === 'azure') {
            // Azure resource-owner uses claim() for user attributes
            /** @var AzureResourceOwner $resourceOwner */
            $email = $resourceOwner->claim('email') ?: $resourceOwner->claim('unique_name');
            $name  = $resourceOwner->claim('name');
        } else {
            // Standard League providers implement getEmail() / getName()
            if (method_exists($resourceOwner, 'getEmail')) {
                $email = $resourceOwner->getEmail();
            }

            if (method_exists($resourceOwner, 'getName')) {
                $name = $resourceOwner->getName();
            }

            // toArray() fallback for GenericProvider or providers without typed methods
            if ($email === null || $name === null) {
                $data = $resourceOwner->toArray();
                $email ??= $data['email'] ?? null;
                $name ??= $data['name'] ?? $data['login'] ?? null;
            }
        }

        return ['id' => $id, 'email' => $email, 'name' => $name];
    }

    /**
     * Find or create a local user and OAuth identity for the resource owner.
     */
    protected function processUser(ResourceOwnerInterface $userProfile, AccessTokenInterface $token): User
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $data     = $this->extractUserData($userProfile);
        $socialId = $data['id'];
        $email    = $data['email'];
        $name     = $data['name'];

        if (empty($email)) {
            throw new AuthenticationException(lang('Auth.emailNotFoundInOauth'));
        }

        $type     = 'oauth_' . $this->providerName;
        $identity = $identityModel->where('type', $type)
            ->where('secret', $socialId)
            ->first();

        $user = null;

        if ($identity) {
            /** @var UserIdentity $identity */
            $user = $identity->user();
        } else {
            $provider = auth()->getProvider();
            $user     = $provider->findByCredentials(['email' => $email]);

            if (! $user instanceof User) {
                $username = explode('@', $email)[0] . '_' . bin2hex(random_bytes(3));
                $user     = new User([
                    'username' => $username,
                    'active'   => true,
                ]);
                $provider->save($user);
                $user = $provider->findById($provider->getInsertID());

                $provider->addToDefaultGroup($user);
            }

            $identityModel->insert([
                'user_id' => $user->id,
                'type'    => $type,
                'name'    => $name ?? $email,
                'secret'  => $socialId,
                'secret2' => $token->getToken(),
                'extra'   => $token->getRefreshToken(),
                'expires' => $token->getExpires() ? Time::createFromTimestamp($token->getExpires()) : null,
            ]);
        }

        auth()->login($user);

        return $user;
    }
}

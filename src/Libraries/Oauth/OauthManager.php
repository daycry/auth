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

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Config\AuthOAuth as AuthConfig;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Entities\UserIdentity;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Libraries\Oauth\ProfileResolver\ProfileResolverFactory;
use Daycry\Auth\Models\OAuthTokenRepository;
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
use Throwable;

class OauthManager
{
    protected AbstractProvider $provider;
    protected string $providerName;
    private ?OAuthTokenRepository $repository = null;

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

    public function __construct(protected AuthConfig $config)
    {
    }

    /**
     * Lazy-initialise the OAuth token repository.
     */
    private function getRepository(): OAuthTokenRepository
    {
        return $this->repository ??= new OAuthTokenRepository(
            $this->getIdentityModel(),
        );
    }

    /**
     * Centralised model() call — avoids repeating it across methods.
     */
    private function getIdentityModel(): UserIdentityModel
    {
        /** @var UserIdentityModel */
        return model(UserIdentityModel::class);
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

        $repo     = $this->getRepository();
        $identity = $repo->findByUserAndProvider((int) $user->id, $this->providerName);

        if ($identity === null || empty($identity->extra)) {
            return null;
        }

        $extraData    = $repo->parseExtra($identity->extra);
        $refreshToken = $extraData['refresh_token'] ?? null;

        if (empty($refreshToken)) {
            return null;
        }

        try {
            $grant = new RefreshToken();
            $token = $this->provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);

            $identity->secret2 = $token->getToken();

            if ($token->getRefreshToken()) {
                $extraData['refresh_token'] = $token->getRefreshToken();
            }

            // Update scopes if the refreshed token includes them
            $tokenValues = $token->getValues();
            if (isset($tokenValues['scope'])) {
                $extraData['scopes_granted'] = is_string($tokenValues['scope'])
                    ? explode(' ', $tokenValues['scope'])
                    : (array) $tokenValues['scope'];
            }

            $identity->extra = json_encode($extraData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($token->getExpires()) {
                $identity->expires = Time::createFromTimestamp($token->getExpires());
            }

            $repo->updateOAuthIdentity($identity);

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

        // CSRF check — reject empty state and use timing-safe comparison.
        if (
            $state === ''
            || ! is_string($sessionState)
            || $sessionState === ''
            || ! hash_equals($sessionState, $state)
        ) {
            session()->remove('oauth2state');

            throw new AuthenticationException(lang('Auth.invalidOauthState'));
        }

        session()->remove('oauth2state');

        if ($code === '') {
            throw new AuthenticationException(lang('Auth.invalidOauthState'));
        }

        try {
            $token       = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $userProfile = $this->provider->getResourceOwner($token);
            $profileData = $this->fetchProfileFields($token, $userProfile);

            $user = $this->processUser($userProfile, $token, $profileData);

            Events::trigger('oauth-login', $user, $this->providerName);

            if ($profileData !== []) {
                Events::trigger('oauth-profile-fetched', $user, $this->providerName, $profileData);
            }

            return $user;
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
     * @return array{id: string, email: string|null, name: string|null, email_verified: bool}
     */
    protected function extractUserData(ResourceOwnerInterface $resourceOwner): array
    {
        $id            = (string) $resourceOwner->getId();
        $email         = null;
        $name          = null;
        $emailVerified = false;

        if ($this->providerName === 'azure') {
            // Azure resource-owner uses claim() for user attributes
            /** @var AzureResourceOwner $resourceOwner */
            $email         = $resourceOwner->claim('email') ?: $resourceOwner->claim('unique_name');
            $name          = $resourceOwner->claim('name');
            $emailVerified = self::isClaimTrue($resourceOwner->claim('email_verified'));
        } else {
            // Standard League providers implement getEmail() / getName()
            if (method_exists($resourceOwner, 'getEmail')) {
                $email = $resourceOwner->getEmail();
            }

            if (method_exists($resourceOwner, 'getName')) {
                $name = $resourceOwner->getName();
            }

            // toArray() exposes the raw claims for GenericProvider/OIDC providers
            // and carries the verified-email signal we need below.
            $data = $resourceOwner->toArray();
            $email ??= $data['email'] ?? null;
            $name ??= $data['name'] ?? $data['login'] ?? null;
            // OIDC standard `email_verified` (Google, generic OIDC) and the
            // legacy Google `verified_email` field.
            $emailVerified = self::isClaimTrue($data['email_verified'] ?? $data['verified_email'] ?? null);
        }

        return ['id' => $id, 'email' => $email, 'name' => $name, 'email_verified' => $emailVerified];
    }

    /**
     * Normalises a verified-email claim that providers express inconsistently
     * (bool true, int 1, or the strings "1"/"true") into a strict boolean.
     */
    private static function isClaimTrue(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || (is_string($value) && strtolower($value) === 'true');
    }

    /**
     * Find or create a local user and OAuth identity for the resource owner.
     *
     * @param array<string, mixed> $profileData Extra profile fields from the resolver
     */
    protected function processUser(ResourceOwnerInterface $userProfile, AccessTokenInterface $token, array $profileData = []): User
    {
        $repo = $this->getRepository();

        $data          = $this->extractUserData($userProfile);
        $socialId      = $data['id'];
        $email         = $data['email'];
        $name          = $data['name'];
        $emailVerified = $data['email_verified'];

        if (empty($email)) {
            throw new AuthenticationException(lang('Auth.emailNotFoundInOauth'));
        }

        $extraData = ['refresh_token' => $token->getRefreshToken()];

        // Scopes (RFC 6749 §3.3: space-delimited)
        $tokenValues = $token->getValues();
        if (isset($tokenValues['scope'])) {
            $extraData['scopes_granted'] = is_string($tokenValues['scope'])
                ? explode(' ', $tokenValues['scope'])
                : (array) $tokenValues['scope'];
        }

        if ($profileData !== []) {
            $extraData['profile']            = $profileData;
            $extraData['profile_fetched_at'] = Time::now()->toDateTimeString();
        }

        $extraJson = json_encode($extraData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // Explicit linking mode: an authenticated user is attaching this provider
        // to their own account (initiated via OauthController::link). This skips
        // the e-mail-merge path entirely and does not require a verified e-mail,
        // because the user is already authenticated and acting deliberately.
        $linkUserId = (int) (session('oauth_link_user_id') ?? 0);
        session()->remove('oauth_link_user_id');

        if ($linkUserId > 0) {
            return $this->linkProviderToUser($linkUserId, $socialId, $name, $email, $extraJson, $token);
        }

        $identity = $repo->findByProviderAndSocialId($this->providerName, $socialId);

        $user = null;

        if ($identity instanceof UserIdentity) {
            /** @var UserIdentity $identity */
            $user = $identity->user();

            // Update token and profile data on re-login
            $identity->secret2 = $token->getToken();
            $identity->extra   = $extraJson;

            if ($token->getExpires()) {
                $identity->expires = Time::createFromTimestamp($token->getExpires());
            }

            $repo->updateOAuthIdentity($identity);
        } else {
            $provider = auth()->getProvider();
            $user     = $provider->findByCredentials(['email' => $email]);

            if ($user instanceof User) {
                // Linking a social identity to an EXISTING local account. Require
                // the provider to assert the e-mail is verified — otherwise an
                // attacker who registers a social account with the victim's e-mail
                // would be silently logged in as the victim (unverified-email
                // account takeover). Operators may opt in per provider.
                $allowUnverified = (bool) ($this->config->providers[$this->providerName]['allowUnverifiedEmailLink'] ?? false);

                if (! $emailVerified && ! $allowUnverified) {
                    throw new AuthenticationException(lang('Auth.oauthEmailUnverified'));
                }
            } else {
                $username = explode('@', $email)[0] . '_' . bin2hex(random_bytes(3));
                $user     = new User([
                    'username' => $username,
                    'active'   => true,
                ]);
                $provider->save($user);
                $user = $provider->findById($provider->getInsertID());

                $provider->addToDefaultGroup($user);
            }

            $repo->createOAuthIdentity((int) $user->id, $this->providerName, [
                'name'    => $name ?? $email,
                'secret'  => $socialId,
                'secret2' => $token->getToken(),
                'extra'   => $extraJson,
                'expires' => $token->getExpires() ? Time::createFromTimestamp($token->getExpires()) : null,
            ]);
        }

        auth()->login($user);

        return $user;
    }

    /**
     * Links the current provider's social identity to an already-authenticated
     * user (explicit linking). Refuses when the social account is already bound
     * to a different local user.
     */
    private function linkProviderToUser(int $userId, string $socialId, ?string $name, string $email, string $extraJson, AccessTokenInterface $token): User
    {
        $repo     = $this->getRepository();
        $provider = auth()->getProvider();

        $user = $provider->findById($userId);

        if (! $user instanceof User) {
            throw new AuthenticationException(lang('Auth.invalidUser'));
        }

        $existing = $repo->findByProviderAndSocialId($this->providerName, $socialId);

        if ($existing instanceof UserIdentity) {
            if ((int) $existing->user_id !== $userId) {
                // Social account already linked to a different local user.
                throw new AuthenticationException(lang('Auth.oauthAlreadyLinked'));
            }

            // Already linked to this user — refresh the stored token/profile.
            $existing->secret2 = $token->getToken();
            $existing->extra   = $extraJson;

            if ($token->getExpires()) {
                $existing->expires = Time::createFromTimestamp($token->getExpires());
            }

            $repo->updateOAuthIdentity($existing);
        } else {
            $repo->createOAuthIdentity($userId, $this->providerName, [
                'name'    => $name ?? $email,
                'secret'  => $socialId,
                'secret2' => $token->getToken(),
                'extra'   => $extraJson,
                'expires' => $token->getExpires() ? Time::createFromTimestamp($token->getExpires()) : null,
            ]);
        }

        auth()->login($user);

        return $user;
    }

    /**
     * Fetch additional profile fields using the appropriate resolver.
     *
     * @return array<string, mixed>
     */
    private function fetchProfileFields(AccessTokenInterface $token, ResourceOwnerInterface $resourceOwner): array
    {
        $providerConfig = $this->config->providers[$this->providerName] ?? [];
        $fields         = $providerConfig['fields'] ?? [];

        if ($fields === []) {
            return [];
        }

        try {
            return ProfileResolverFactory::create($this->providerName, $providerConfig)
                ->fetchFields($this->provider, $token, $resourceOwner, $fields, $providerConfig);
        } catch (Throwable $e) {
            log_message('warning', 'OAuth profile fetch failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Get the stored profile data for a user's OAuth identity.
     *
     * @return array<string, mixed>
     */
    public function getProfileData(User $user): array
    {
        return $this->getRepository()->getProfileData((int) $user->id, $this->providerName);
    }
}

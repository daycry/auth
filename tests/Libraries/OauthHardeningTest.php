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

namespace Tests\Libraries;

use Daycry\Auth\Config\AuthOAuth;
use Daycry\Auth\Exceptions\AuthenticationException;
use Daycry\Auth\Libraries\Oauth\OauthManager;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class OauthHardeningTest extends TestCase
{
    private function config(array $extra = []): AuthOAuth
    {
        $config            = new AuthOAuth();
        $config->providers = ['test' => array_replace([
            'clientId'                => 'client', 'clientSecret' => 'secret',
            'redirectUri'             => 'https://app.example/callback',
            'urlAuthorize'            => 'https://id.example/authorize',
            'urlAccessToken'          => 'https://id.example/token',
            'urlResourceOwnerDetails' => 'https://id.example/me',
        ], $extra)];

        return $config;
    }

    public function testRedirectAddsS256ChallengeAndKeepsVerifierOnlyInSession(): void
    {
        $manager  = new OauthManager($this->config());
        $location = $manager->setProvider('test')->redirect()->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $flow = session('oauth2flow');
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertStringNotContainsString($flow['verifier'], $location);
    }

    public function testCallbackRestoresVerifierAndConsumesItOnFailure(): void
    {
        $config  = $this->config();
        $manager = new OauthManager($config);
        $manager->setProvider('test')->redirect();
        $state    = session('oauth2state');
        $verifier = session('oauth2flow')['verifier'];
        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects($this->once())->method('setPkceCode')->with($verifier);
        $provider->expects($this->once())->method('getAccessToken')->with('authorization_code', ['code' => 'code'])->willThrowException(new RuntimeException('transport failed'));
        $manager->setProviderInstance($provider, 'test');

        try {
            $manager->handleCallback('code', $state);
            $this->fail('Expected transport failure');
        } catch (RuntimeException $e) {
            $this->assertSame('transport failed', $e->getMessage());
        }
        $this->assertNull(session('oauth2flow'));
        $this->assertNull(session('oauth2state'));
    }

    public function testCallbackRejectsWrongIssuerBeforeExchangingCode(): void
    {
        $manager = new OauthManager($this->config(['issuer' => 'https://id.example']));
        $manager->setProvider('test')->redirect();
        $state    = session('oauth2state');
        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects($this->never())->method('getAccessToken');
        $manager->setProviderInstance($provider, 'test');

        $this->expectException(AuthenticationException::class);
        $manager->handleCallback('code', $state, 'https://attacker.example');
    }

    public function testOidcRejectsWrongNonceBeforeFetchingProfile(): void
    {
        $manager = new OauthManager($this->config(['issuer' => 'https://id.example', 'oidc' => true]));
        $manager->setProvider('test')->redirect();
        $state    = session('oauth2state');
        $claims   = ['iss' => 'https://id.example', 'aud' => 'client', 'exp' => time() + 60, 'iat' => time(), 'sub' => '123', 'nonce' => 'wrong'];
        $jwt      = 'eyJhbGciOiJSUzI1NiJ9.' . rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=') . '.signature';
        $provider = $this->createMock(AbstractProvider::class);
        $provider->method('setPkceCode');
        $provider->method('getBaseAccessTokenUrl')->willReturn('https://id.example/token');
        $provider->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'a', 'id_token' => $jwt]));
        $provider->expects($this->never())->method('getResourceOwner');
        $manager->setProviderInstance($provider, 'test');

        $this->expectException(AuthenticationException::class);
        $manager->handleCallback('code', $state, 'https://id.example');
    }
}

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

namespace Daycry\Auth\Config;

use CodeIgniter\Config\BaseConfig;

class AuthOAuth extends BaseConfig
{
    /**
     * ////////////////////////////////////////////////////////////////////
     * OAUTH PROVIDERS
     *
     * Supported provider aliases (require the matching League package):
     *
     *   'azure'    → thenetworg/oauth2-azure   (already required)
     *   'google'   → league/oauth2-google
     *   'facebook' → league/oauth2-facebook
     *   'github'   → league/oauth2-github
     *
     * Any other alias falls back to league/oauth2-client GenericProvider and
     * requires the urlAuthorize / urlAccessToken / urlResourceOwnerDetails keys.
     *
     * Routes: GET  /auth/oauth/{alias}           → OauthController::redirect()
     *         GET  /auth/oauth/{alias}/callback  → OauthController::callback()
     *
     * @var array<string, array<string, mixed>>
     */
    public array $providers = [
        // ----------------------------------------------------------------
        // Microsoft Azure AD / Entra ID   (thenetworg/oauth2-azure)
        // ----------------------------------------------------------------
        'azure' => [
            'clientId'               => 'YOUR_AZURE_CLIENT_ID',
            'clientSecret'           => 'YOUR_AZURE_CLIENT_SECRET',
            'redirectUri'            => 'http://localhost:8080/auth/oauth/azure/callback',
            'scopes'                 => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],
            'defaultEndPointVersion' => '2.0',
            'tenant'                 => 'common', // 'common' | 'organizations' | <tenantId>
        ],

        // ----------------------------------------------------------------
        // Google   (league/oauth2-google)
        // ----------------------------------------------------------------
        // 'google' => [
        //     'clientId'     => 'YOUR_GOOGLE_CLIENT_ID',
        //     'clientSecret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        //     'redirectUri'  => 'http://localhost:8080/auth/oauth/google/callback',
        //     // 'hostedDomain' => 'example.com', // restrict to a G Suite domain (optional)
        // ],

        // ----------------------------------------------------------------
        // Facebook   (league/oauth2-facebook)
        // ----------------------------------------------------------------
        // 'facebook' => [
        //     'clientId'        => 'YOUR_FACEBOOK_APP_ID',
        //     'clientSecret'    => 'YOUR_FACEBOOK_APP_SECRET',
        //     'redirectUri'     => 'http://localhost:8080/auth/oauth/facebook/callback',
        //     'graphApiVersion' => 'v19.0',
        // ],

        // ----------------------------------------------------------------
        // GitHub   (league/oauth2-github)
        // ----------------------------------------------------------------
        // 'github' => [
        //     'clientId'     => 'YOUR_GITHUB_CLIENT_ID',
        //     'clientSecret' => 'YOUR_GITHUB_CLIENT_SECRET',
        //     'redirectUri'  => 'http://localhost:8080/auth/oauth/github/callback',
        //     // Note: email may be null for GitHub users with a private email.
        // ],

        // ----------------------------------------------------------------
        // Generic (any provider via league/oauth2-client GenericProvider)
        // ----------------------------------------------------------------
        // 'my_provider' => [
        //     'clientId'                => 'YOUR_CLIENT_ID',
        //     'clientSecret'            => 'YOUR_CLIENT_SECRET',
        //     'redirectUri'             => 'http://localhost:8080/auth/oauth/my_provider/callback',
        //     'urlAuthorize'            => 'https://provider.example.com/oauth/authorize',
        //     'urlAccessToken'          => 'https://provider.example.com/oauth/token',
        //     'urlResourceOwnerDetails' => 'https://provider.example.com/api/user',
        // ],
    ];
}

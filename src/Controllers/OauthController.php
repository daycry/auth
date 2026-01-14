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

use Daycry\Auth\Libraries\Oauth\OauthManager;
use Exception;

class OauthController extends BaseAuthController
{
    protected $helpers = ['setting', 'url'];

    /**
     * Get rules for validation
     */
    protected function getValidationRules(): array
    {
        return [];
    }

    public function redirect(string $provider)
    {
        $config  = config('Auth');
        $manager = new OauthManager($config);

        return $manager->setProvider($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $config  = config('Auth');
        $manager = new OauthManager($config);

        try {
            $user = $manager->setProvider($provider)->handleCallback(
                $this->request->getGet('code') ?? '',
                $this->request->getGet('state') ?? '',
            );

            return redirect()->to(config('Auth')->loginRedirect());
        } catch (Exception $e) {
            return redirect()->to(config('Auth')->loginPage())->with('error', $e->getMessage());
        }
    }
}

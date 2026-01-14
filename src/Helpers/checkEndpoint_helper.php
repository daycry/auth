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

use CodeIgniter\Config\Services;
use Daycry\Auth\Entities\Api;
use Daycry\Auth\Entities\Controller;
use Daycry\Auth\Entities\Endpoint;
use Daycry\Auth\Models\ApiModel;

if (! function_exists('checkEndpoint')) {
    /**
     * Provides an endpoint for the actual request
     */
    function checkEndpoint(): ?Endpoint
    {
        if (! service('settings')->get('Auth.enableDiscovery')) {
            return null;
        }

        $apiModel = model(ApiModel::class);
        /** @var Api|null $api */
        $api = $apiModel->where('url', site_url())->first();

        if ($api) {
            $router      = Services::router();
            $controllers = $api->getControllers($router->controllerName());
            /** @var Controller|null $controller */
            $controller = ($controllers) ? $controllers[0] : null;
            if ($controller) {
                $endpoints = $controller->getEndpoints($controller->controller . '::' . $router->methodName());

                return ($endpoints) ? $endpoints[0] : null;
            }
        }

        return null;
    }
}

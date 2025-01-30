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

namespace Tests\Support;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Daycry\Auth\Test\AuthenticationTesting;

/**
 * @internal
 */
abstract class FilterTestCase extends DatabaseTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected string $routeFilter;
    protected $namespace;
    protected string $alias;
    protected string $classname;

    protected function setUp(): void
    {
        $_SESSION = [];

        Services::reset(true);
        helper('test');

        parent::setUp();

        // Register our filter
        $this->registerFilter();

        // Add a test route that we can visit to trigger.
        $this->addRoutes();
    }

    private function registerFilter(): void
    {
        /** @var Filter $filterConfig */
        $filterConfig = config('Filters');

        $filterConfig->aliases[$this->alias] = $this->classname;

        Factories::injectMock('filters', 'filters', $filterConfig);
    }

    private function addRoutes(): void
    {
        $routes = service('routes');

        $filterString = isset($this->routeFilter) && ($this->routeFilter !== '' && $this->routeFilter !== '0')
            ? $this->routeFilter
            : $this->alias;

        $routes->group(
            '/',
            ['filter' => $filterString],
            static function ($routes): void {
                $routes->get('protected-route', static function (): void {
                    echo 'Protected';
                });
            },
        );
        $routes->get('open-route', static function (): void {
            echo 'Open';
        });
        $routes->get('login', 'AuthController::login', ['as' => 'login']);
        $routes->get('auth/a/show', 'AuthActionController::show', ['as' => 'auth-action-show']);
        $routes->get('protected-user-route', static function (): void {
            echo 'Protected';
        }, ['filter' => $this->alias . ':users-read']);

        Services::injectMock('routes', $routes);
    }
}

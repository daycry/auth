<?php

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\I18n\Time;
use Daycry\Auth\Entities\Api;
use Daycry\Auth\Entities\Controller;
use Daycry\Auth\Entities\Endpoint;
use Daycry\Auth\Models\ApiModel;
use Daycry\Auth\Models\ControllerModel;
use Daycry\Auth\Models\EndpointModel;
use Daycry\ClassFinder\ClassFinder;
use Daycry\ClassFinder\Config\ClassFinder as ClassFinderConfig;
use ReflectionClass;
use ReflectionMethod;

class DiscoverCommand extends BaseCommand
{
    protected $group       = 'Auth';
    protected $name        = 'auth:discover';
    protected $description = 'Discover classes from namespace to import in database.';
    protected Time $timeStart;
    protected BaseConfig $config;
    protected array $allClasses = [];

    public function run(array $params)
    {
        $this->timeStart = Time::now()->subSeconds(1);
        /** @var ClassFinderConfig $finderConfig */
        $finderConfig                  = config('ClassFinder');
        $finderConfig->finder['files'] = false;

        $api = $this->_checkApiModel();

        foreach (service('settings')->get('Auth.namespaceScope') as $namespace) {
            // remove "\" for search in class-finder
            $namespace = (mb_substr($namespace, 0, 1) === '\\') ? mb_substr($namespace, 1) : $namespace;

            $classes = (new ClassFinder($finderConfig))->getClassesInNamespace($namespace, ClassFinder::RECURSIVE_MODE | ClassFinder::ALLOW_CLASSES);
            if ($classes) {
                foreach ($classes as $class) {
                    $this->allClasses[] = '\\' . $class;

                    $methods = $this->_getMethodsFromCLass($class);

                    $class = (mb_substr($class, 0, 1) !== '\\') ? '\\' . $class : $class;

                    $this->_checkClassController($api, $class, $methods);
                }

                unset($classes);
            }
        }

        $controllerModel = new ControllerModel();
        $allControllers  = $controllerModel->where('api_id', $api->id)->findColumn('controller');
        if ($allControllers) {
            $forRemove = array_diff($allControllers, $this->allClasses);

            foreach ($forRemove as $remove) {
                $controller = $controllerModel->where('api_id', $api->id)->where('controller', $remove)->first();
                if ($controller) {
                    $controllerModel->where('id', $controller->id)->delete();
                }
            }
        }

        CLI::write('**** FINISHED. ****', 'white', 'green');
    }

    private function _checkApiModel(): Api
    {
        $apiModel = new ApiModel();
        /** @var ?Api $api */
        $api = $apiModel->where('url', site_url())->first();

        if (! $api) {
            $api = new Api();
            $api->fill(['url' => site_url()]);
            $apiModel->save($api);
            $api->id = $apiModel->getInsertID();
        } else {
            $api->fill(['checked_at' => Time::now()]);
            $apiModel->save($api);
        }

        return $api;
    }

    private function _getMethodsFromCLass($namespace): array
    {
        $f       = new ReflectionClass($namespace);
        $methods = [];

        $namespaceConverted = (mb_substr($namespace, 0, 1) !== '\\') ? '\\' . $namespace : $namespace;

        foreach ($f->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if (strpos($m->name, '__') !== 0 && $m->class === $namespace && ! in_array($m->name, service('settings')->get('Auth.excludeMethods'), true)) {
                $methods[] = $namespaceConverted . '::' . $m->name;
            }
        }

        return $methods;
    }

    private function _checkClassController(Api $api, string $class, array $methods = [])
    {
        $controllerModel = new ControllerModel();
        $controller      = $controllerModel->where('api_id', $api->id)->where('controller', $class)->first();

        if (! $controller) {
            $controller = new Controller();
            $controller->fill(['api_id' => $api->id, 'controller' => $class]);
            $controllerModel->save($controller);
            $controller->id = $controllerModel->getInsertID();
        }

        $endpointModel = new EndpointModel();

        $allMethods = (new EndpointModel())->where('controller_id', $controller->id)->findColumn('method');

        foreach ($methods as $method) {
            $endpoint = $endpointModel->where('controller_id', $controller->id)->where('method', $method)->first();

            if (! $endpoint) {
                $endpoint = new Endpoint();
                $endpoint->fill(['controller_id' => $controller->id, 'method' => $method]);
                $endpointModel->save($endpoint);
                $endpoint->id = $endpointModel->getInsertID();
            }

            $endpoint->checked_at = Time::now();
            $endpointModel->save($endpoint);
        }

        if ($allMethods) {
            $forRemove = array_diff($allMethods, $methods);

            foreach ($forRemove as $remove) {
                $endpoint = $endpointModel->where('controller_id', $controller->id)->where('method', $remove)->first();
                if ($endpoint) {
                    $endpointModel->where('id', $endpoint->id)->delete();
                }
            }
        }

        $controller->checked_at = Time::now();
        $controllerModel->save($controller);
    }
}

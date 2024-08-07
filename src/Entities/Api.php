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

namespace Daycry\Auth\Entities;

use Daycry\Auth\Models\ControllerModel;

class Api extends Entity
{
    /**
     * @var list<Controller>|null
     */
    private ?array $controllers = null;

    /**
     * @var         list<string>
     * @phpstan-var list<string>
     * @psalm-var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'checked_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id'  => '?integer',
        'url' => 'string',
    ];

    /**
     * Accessor method for this api's Controller objects.
     * Will populate if they don't exist.
     *
     * @param string $controller 'all' returns all controllers.
     *
     * @return list<Controller>
     */
    public function getControllers(string $controller = 'all'): array
    {
        $this->populateControllers();

        if ($controller === 'all') {
            return $this->controllers;
        }

        $controllers = [];

        foreach ($this->controllers as $c) {
            if ($c->controller === $controller) {
                $controllers[] = $c;
            }
        }

        return $controllers;
    }

    /**
     * ensures that all of the api's controllers are loaded
     * into the instance for faster access later.
     */
    private function populateControllers(): void
    {
        if ($this->controllers === null) {
            /** @var ControllerModel $controllerModel */
            $controllerModel = model(ControllerModel::class);

            $this->controllers = $controllerModel->getControllers($this);
        }
    }
}

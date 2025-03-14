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

namespace Daycry\Auth\Traits;

use CodeIgniter\Validation\ValidationInterface;
use Config\Services;
use Config\Validation as ValidationConfig;
use Daycry\Auth\Exceptions\ValidationException;

trait Validation
{
    /**
     * Once validation has been run, will hold the Validation instance.
     *
     * @var ValidationInterface|null
     */
    protected $validator;

    protected function dataValidation(array|string $rules, array|object $data, ?ValidationConfig $config = null, bool $getShared = true, ?string $dbGroup = null): array
    {
        $config ??= config('Validation');

        $this->validator = Services::validation($config, $getShared);

        return $this->runValidate($rules, $data, $dbGroup);
    }

    protected function requestValidation(array|string $rules, ?ValidationConfig $config = null, bool $getShared = true, ?string $dbGroup = null): array
    {
        $config ??= config('Validation');

        $this->validator = Services::validation($config, $getShared)->withRequest(Services::request());

        return $this->runValidate($rules, null, $dbGroup);
    }

    private function runValidate(array|string $rules, array|object|null $data = null, ?string $dbGroup = null)
    {
        if ($data !== null) {
            if (is_object($data)) {
                $data = get_object_vars($data);
            }
        }

        if (is_string($rules)) {
            $this->validator->setRuleGroup($rules);
        } else {
            $this->validator->setRules($rules);
        }

        if (! $this->validator->run($data, null, $dbGroup)) {
            throw ValidationException::validationData();
        }

        return $this->validator->getValidated();
    }
}

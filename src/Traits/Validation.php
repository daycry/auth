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

    protected function dataValidation(string $rules, object|array $data, ?ValidationConfig $config = null, bool $getShared = true, bool $filter = false, ?string $dbGroup = null)
    {
        $config ??= config('Validation');

        $this->validator = Services::validation($config, $getShared);

        $this->runValidate($rules, $data, $config, $filter, $dbGroup);
    }

    protected function requestValidation(string $rules, ?ValidationConfig $config = null, bool $getShared = true, bool $filter = false, ?string $dbGroup = null)
    {
        $config ??= config('Validation');

        $this->validator = Services::validation($config, $getShared)->withRequest($this->request);

        $this->runValidate($rules, null, $config, $filter, $dbGroup);
    }

    private function runValidate(string $rules, object|array|null $data = null, ?ValidationConfig $config = null, bool $filter = false, ?string $dbGroup = null)
    {
        if ($data !== null) {
            $data = get_object_vars($data);
        }

        $this->validator->setRuleGroup($rules);

        if (! $this->validator->run($data, null, $dbGroup)) {
            throw ValidationException::validationData();
        }

        $validatedData = $this->validator->getValidated();

        if ($filter) {
            if ($validatedData) {
                foreach ($validatedData as $key => $value) {
                    if (! array_key_exists($key, $config->{$rules})) {
                        throw ValidationException::validationtMethodParamsError($key);
                    }
                }
            }
        }
    }
}

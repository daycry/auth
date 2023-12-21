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

use Config\Services;
use Config\Validation as ValidationCOnfig;
use Daycry\Auth\Exceptions\ValidationException;

trait Validation
{
    protected function validation(string $rules, $data = null, ?ValidationCOnfig $config = null, bool $getShared = true, bool $filter = false)
    {
        $config ??= config('Validation');
        $data ??= $this->content;

        $this->validator = Services::validation($config, $getShared);

        $content = json_decode(json_encode($data), true);
        if (! $this->validator->run($content, $rules)) {
            throw ValidationException::validationData();
        }

        if ($filter) {
            if ($data) {
                foreach ($data as $key => $value) {
                    if (! array_key_exists($key, $config->{$rules})) {
                        throw ValidationException::validationtMethodParamsError($key);
                    }
                }
            }
        }
    }
}

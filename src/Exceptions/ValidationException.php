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

namespace Daycry\Auth\Exceptions;

use Daycry\Exceptions\Exceptions\RuntimeException;

class ValidationException extends RuntimeException
{
    public static function validationtMethodParamsError($param)
    {
        $parser = \Config\Services::parser();

        return new self($parser->setData(['param' => $param])->renderString(lang('Auth.invalidParamsForMethod')));
    }

    public static function validationData()
    {
        return new self('');
    }
}

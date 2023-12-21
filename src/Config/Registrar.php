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

use Daycry\Auth\Collectors\Auth;
use Daycry\Auth\Filters\AuthFilter;
use Daycry\Auth\Filters\ChainAuthFilter;
use Daycry\Auth\Filters\ForcePasswordResetFilter;
use Daycry\Auth\Filters\GroupFilter;
use Daycry\Auth\Filters\PermissionFilter;
use Daycry\Auth\Filters\RatesFilter;
use Daycry\Auth\Validation\PasswordValidationRules as PasswordRules;

class Registrar
{
    /**
     * Registers the Shield filters.
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'auth'        => AuthFilter::class,
                'chain'       => ChainAuthFilter::class,
                'rates'       => RatesFilter::class,
                'group'       => GroupFilter::class,
                'permission'  => PermissionFilter::class,
                'force-reset' => ForcePasswordResetFilter::class,
            ],
        ];
    }

    public static function Validation(): array
    {
        return [
            'ruleSets' => [
                PasswordRules::class,
            ],
        ];
    }

    public static function Toolbar(): array
    {
        return [
            'collectors' => [
                Auth::class,
            ],
        ];
    }

    public static function Generators(): array
    {
        return [
            'views' => [
                'shield:model' => 'CodeIgniter\Shield\Commands\Generators\Views\usermodel.tpl.php',
            ],
        ];
    }
}

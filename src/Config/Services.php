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

use CodeIgniter\Config\BaseService;
use Daycry\Auth\Auth;
use Daycry\Auth\Authentication\Passwords;
use Daycry\Auth\Config\Auth as AuthConfig;
use Daycry\Auth\Libraries\Logger;

class Services extends BaseService
{
    /**
     * The base auth class
     */
    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return self::getSharedInstance('auth');
        }

        /** @var AuthConfig $config */
        $config = config('Auth');

        return new Auth($config);
    }

    /**
     * Password utilities.
     */
    public static function passwords(bool $getShared = true): Passwords
    {
        if ($getShared) {
            return self::getSharedInstance('passwords');
        }

        return new Passwords(config('Auth'));
    }

    /**
     * The restful log class
     */
    public static function log(bool $getShared = true): Logger
    {
        if ($getShared) {
            return self::getSharedInstance('log');
        }

        helper('checkEndpoint');

        return new Logger(checkEndpoint());
    }
}

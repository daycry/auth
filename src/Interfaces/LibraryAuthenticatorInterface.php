<?php

declare(strict_types=1);

namespace Daycry\Auth\Interfaces;

use Daycry\Auth\Models\UserModel;
use Daycry\Auth\Result;

interface LibraryAuthenticatorInterface
{
    public function __construct(UserModel $provider);
    public function check(array $credentials): Result;
}

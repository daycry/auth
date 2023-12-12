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

use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Auth\Config\Auth;

/**
 * @internal
 */
abstract class DatabaseTestCase extends TestCase
{
    use DatabaseTestTrait;

    protected $namespace = '\Daycry\Auth';

    /**
     * Auth Table names
     */
    protected array $tables;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Auth $authConfig */
        $authConfig   = config('Auth');
        $this->tables = $authConfig->tables;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

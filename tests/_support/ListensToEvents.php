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

use CodeIgniter\Events\Events;

/**
 * Registers test listeners and removes exactly those afterwards.
 *
 * Events are static: `Events::removeAllListeners()` would also drop the
 * listeners the library registers itself (e.g. `suspicious-login` in
 * src/Config/Events.php) for every later test.
 */
trait ListensToEvents
{
    /**
     * @var list<array{string, callable}>
     */
    private array $testListeners = [];

    protected function listen(string $event, callable $listener): void
    {
        Events::on($event, $listener);

        $this->testListeners[] = [$event, $listener];
    }

    protected function stopListening(): void
    {
        foreach ($this->testListeners as [$event, $listener]) {
            Events::removeListener($event, $listener);
        }

        $this->testListeners = [];
    }
}

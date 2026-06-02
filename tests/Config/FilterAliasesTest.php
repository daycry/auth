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

namespace Tests\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

/**
 * Guards the documented filter aliases: every alias the docs tell users to
 * reference must be auto-registered by Registrar and resolve to a real class.
 *
 * @internal
 */
final class FilterAliasesTest extends TestCase
{
    /**
     * The aliases auto-registered by Registrar::Filters() and documented in
     * docs/04-filters.md. If this list and the Registrar drift, the docs (and
     * users copying them) break — so assert they stay in sync.
     *
     * @return iterable<string, array{string}>
     */
    public static function aliasProvider(): iterable
    {
        foreach ([
            'auth',
            'basic-auth',
            'chain',
            'rates',
            'group',
            'permission',
            'gate',
            'force-reset',
            'token-scope',
            'password-age',
            'password-confirm',
        ] as $alias) {
            yield $alias => [$alias];
        }
    }

    #[DataProvider('aliasProvider')]
    public function testDocumentedAliasResolvesToExistingClass(string $alias): void
    {
        /** @var \Config\Filters $filtersConfig */
        $filtersConfig = config('Filters');

        $this->assertArrayHasKey(
            $alias,
            $filtersConfig->aliases,
            "Filter alias '{$alias}' must be auto-registered by Registrar.",
        );

        $class = $filtersConfig->aliases[$alias];

        $this->assertTrue(
            class_exists($class),
            "Filter class for alias '{$alias}' must exist: {$class}",
        );
    }
}

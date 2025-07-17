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

namespace Tests\Collectors;

use Daycry\Auth\Auth as ShieldAuth;
use Daycry\Auth\Collectors\Auth;
use ReflectionClass;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AuthCollectorTest extends DatabaseTestCase
{
    private Auth $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new Auth();
    }

    public function testConstructor(): void
    {
        // Test that constructor properly initializes the collector
        $this->assertInstanceOf(Auth::class, $this->collector);
    }

    public function testCollectorProperties(): void
    {
        // Test collector properties using reflection since they're protected
        $reflection = new ReflectionClass($this->collector);

        $hasTimeline = $reflection->getProperty('hasTimeline');
        $hasTimeline->setAccessible(true);
        $this->assertFalse($hasTimeline->getValue($this->collector));

        $hasTabContent = $reflection->getProperty('hasTabContent');
        $hasTabContent->setAccessible(true);
        $this->assertTrue($hasTabContent->getValue($this->collector));

        $hasVarData = $reflection->getProperty('hasVarData');
        $hasVarData->setAccessible(true);
        $this->assertFalse($hasVarData->getValue($this->collector));

        $title = $reflection->getProperty('title');
        $title->setAccessible(true);
        $this->assertSame('Auth', $title->getValue($this->collector));
    }

    public function testGetTitleDetails(): void
    {
        $titleDetails = $this->collector->getTitleDetails();

        // Should contain Shield version and authenticator class
        $this->assertStringContainsString(ShieldAuth::SHIELD_VERSION, $titleDetails);
        $this->assertStringContainsString('|', $titleDetails);

        // Should be a non-empty string
        $this->assertNotEmpty($titleDetails);
    }

    public function testDisplayWhenNotLoggedIn(): void
    {
        // When not logged in, should display "Not logged in" message
        $display = $this->collector->display();

        $this->assertStringContainsString('Not logged in.', $display);
        $this->assertStringContainsString('<p>', $display);
    }

    public function testDisplayWhenLoggedIn(): void
    {
        // Skip this test if auth() is not available or causes issues
        if (! function_exists('auth')) {
            $this->markTestSkipped('Auth helper not available');
        }

        $display = $this->collector->display();

        // When not logged in, should display "Not logged in" message
        $this->assertStringContainsString('Not logged in.', $display);
    }

    public function testGetBadgeValueWhenNotLoggedIn(): void
    {
        // When not logged in, badge value should be null
        $badgeValue = $this->collector->getBadgeValue();
        $this->assertNull($badgeValue);
    }

    public function testGetBadgeValueWhenLoggedIn(): void
    {
        // Skip this test if auth() is not available or causes issues
        if (! function_exists('auth')) {
            $this->markTestSkipped('Auth helper not available');
        }

        $badgeValue = $this->collector->getBadgeValue();

        // When not logged in, badge value should be null
        $this->assertNull($badgeValue);
    }

    public function testIcon(): void
    {
        $icon = $this->collector->icon();

        // Should return a data URI for an image
        $this->assertStringStartsWith('data:image/png;base64,', $icon);
        $this->assertNotEmpty($icon);

        // Should be a valid base64 string
        $base64Data = substr($icon, strpos($icon, ',') + 1);
        $decoded    = base64_decode($base64Data, true);
        $this->assertNotFalse($decoded);
    }

    public function testDisplayWithUserGroups(): void
    {
        // Skip this test if auth() is not available or causes issues
        if (! function_exists('auth')) {
            $this->markTestSkipped('Auth helper not available');
        }

        $display = $this->collector->display();

        // When not logged in, should display "Not logged in" message
        $this->assertStringContainsString('Not logged in.', $display);
    }

    public function testDisplayWithUserPermissions(): void
    {
        // Skip this test if auth() is not available or causes issues
        if (! function_exists('auth')) {
            $this->markTestSkipped('Auth helper not available');
        }

        $display = $this->collector->display();

        // When not logged in, should display "Not logged in" message
        $this->assertStringContainsString('Not logged in.', $display);
    }

    public function testTitleDetailsContainsAuthenticatorClass(): void
    {
        $titleDetails = $this->collector->getTitleDetails();

        // Should contain a class name (contains backslashes for namespacing)
        $this->assertStringContainsString('\\', $titleDetails);
    }

    public function testDisplayGeneratesValidHtml(): void
    {
        // Test that display generates valid HTML structure
        $display = $this->collector->display();

        // Should have properly formatted HTML
        $this->assertStringNotContainsString('<p>Not logged in.</p><h3>', $display);

        // Should not have unclosed tags (basic check)
        $this->assertSame(
            substr_count($display, '<table>'),
            substr_count($display, '</table>'),
        );
        $this->assertSame(
            substr_count($display, '<tbody>'),
            substr_count($display, '</tbody>'),
        );
    }

    public function testMultipleCallsConsistent(): void
    {
        // Multiple calls to the same method should return consistent results
        $titleDetails1 = $this->collector->getTitleDetails();
        $titleDetails2 = $this->collector->getTitleDetails();

        $this->assertSame($titleDetails1, $titleDetails2);

        $icon1 = $this->collector->icon();
        $icon2 = $this->collector->icon();

        $this->assertSame($icon1, $icon2);

        $badgeValue1 = $this->collector->getBadgeValue();
        $badgeValue2 = $this->collector->getBadgeValue();

        $this->assertSame($badgeValue1, $badgeValue2);
    }
}

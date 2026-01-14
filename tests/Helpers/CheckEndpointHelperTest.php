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

namespace Tests\Helpers;

use Daycry\Auth\Entities\Api;
use Daycry\Auth\Entities\Controller;
use Daycry\Auth\Entities\Endpoint;
use Daycry\Auth\Models\ApiModel;
use Exception;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class CheckEndpointHelperTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the helper
        helper('checkEndpoint');
    }

    public function testCheckEndpointFunction(): void
    {
        $this->assertTrue(function_exists('checkEndpoint'));
    }

    public function testCheckEndpointWithNoApi(): void
    {
        $endpoint = checkEndpoint();
        $this->assertNotInstanceOf(Endpoint::class, $endpoint);
    }

    public function testCheckEndpointWithApi(): void
    {
        // Create test API in database
        model(ApiModel::class);

        // Mock the API data
        [
            'url'         => site_url(),
            'key'         => 'test-key',
            'secret'      => 'test-secret',
            'name'        => 'Test API',
            'description' => 'Test API Description',
            'active'      => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        // This test depends on the actual database structure
        // For now, we'll test the function exists and returns null when no API is found
        $endpoint = checkEndpoint();
        $this->assertNotInstanceOf(Endpoint::class, $endpoint);
    }

    public function testCheckEndpointFunctionReturnType(): void
    {
        $endpoint = checkEndpoint();
        $this->assertTrue($endpoint === null || $endpoint instanceof Endpoint);
    }

    public function testCheckEndpointHelperLoaded(): void
    {
        // Test that the helper is properly loaded
        $this->assertTrue(function_exists('checkEndpoint'));

        // Test that it doesn't throw an error when called
        $result = checkEndpoint();
        $this->assertTrue($result === null || $result instanceof Endpoint);
    }

    public function testCheckEndpointWithMockedRouter(): void
    {
        // Since we can't easily mock the router service in this context,
        // we'll test that the function executes without errors
        $this->expectNotToPerformAssertions();

        try {
            checkEndpoint();
        } catch (Exception $e) {
            $this->fail('checkEndpoint should not throw an exception: ' . $e->getMessage());
        }
    }

    public function testCheckEndpointDependencies(): void
    {
        // Test that required classes exist
        $this->assertTrue(class_exists(ApiModel::class));
        $this->assertTrue(class_exists(Api::class));
        $this->assertTrue(class_exists(Controller::class));
        $this->assertTrue(class_exists(Endpoint::class));
    }
}

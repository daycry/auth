# 🧪 Testing Guide

Testing is crucial for maintaining the reliability and security of your authentication system. This guide covers how to test applications using Daycry Auth and how to contribute to the library itself.

## 📋 Table of Contents

- [🏃‍♂️ Quick Start](#️-quick-start)
- [🧪 Test Categories](#-test-categories)
- [🔧 Test Setup](#-test-setup)
- [🛡️ Testing Authentication](#️-testing-authentication)
- [👥 Testing Authorization](#-testing-authorization)
- [🎛️ Testing Controllers](#️-testing-controllers)
- [🔍 Testing Filters](#-testing-filters)
- [📊 Testing Models](#-testing-models)
- [🏗️ Testing Traits](#️-testing-traits)
- [🎯 Testing Best Practices](#-testing-best-practices)
- [🚀 Contributing Tests](#-contributing-tests)

## 🏃‍♂️ Quick Start

### Running Tests

```bash
# Run all tests
composer test

# Run specific test class
composer test -- --filter="AuthenticationTest"

# Run tests with coverage
composer test:coverage

# Run tests with verbose output
./vendor/bin/phpunit --verbose
```

### Test Environment Setup

The library ships two ready-to-use base classes under `tests/_support/`:

| Class | Use when |
|-------|---------|
| `Tests\Support\TestCase` | No database needed (unit tests, filter tests) |
| `Tests\Support\DatabaseTestCase` | Tests that read/write the SQLite in-memory database |

Both base classes automatically:
- Reset all CI4 services between tests
- Inject the array settings handler (so `setting()` calls work)
- Inject a fixed AES-256 encryption key (so `service('encrypter')` works — needed for TOTP)
- Seed with `CoreSeeder` (groups, permissions, one default user)

```php
<?php

namespace Tests\Authentication;

use Tests\Support\DatabaseTestCase;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserModel;

class MyAuthTest extends DatabaseTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user via CI4 Fabricator
        $this->user = fake(UserModel::class);
    }
}
```

## 🧪 Test Categories

### Unit Tests
- **Authentication Logic**: Login, logout, password validation
- **Authorization Logic**: Permission checking, group management  
- **Models**: User operations, data validation
- **Entities**: User entity behavior
- **Services**: Auth services functionality

### Integration Tests
- **Controllers**: Full request/response cycles
- **Filters**: Request filtering and security
- **Database**: Data persistence and retrieval
- **Commands**: CLI commands functionality

### Feature Tests
- **User Registration**: Complete registration flow
- **Login Process**: Authentication workflow
- **Password Reset**: Reset functionality
- **Access Control**: Permission-based access

## 🔧 Test Setup

### Base Test Class

```php
<?php
namespace Tests\Authentication;

use Tests\Support\DatabaseTestCase;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserModel;

class MySessionTest extends DatabaseTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Use CI4 Fabricator to create a user with hashed password + email identity
        $this->user = fake(UserModel::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        auth('session')->logout();
    }
}
```

### Mock Configuration

Use the built-in helpers to override config properties for a single test:

```php
// Override Auth config (authenticators, actions, views, routes, session)
$this->inkectMockAttributes(['defaultAuthenticator' => 'jwt']);
$this->inkectMockAttributes(['actions' => ['login' => \Daycry\Auth\Authentication\Actions\Totp2FA::class]]);

// Override AuthSecurity config (passwords, lockout, rate-limit, TOTP, token lifetimes)
$this->inkectMockAttributesSecurity(['userMaxAttempts' => 3]);
$this->inkectMockAttributesSecurity(['minimumPasswordLength' => 12]);

// Override AuthOAuth config (provider definitions)
$this->inkectMockAttributesOAuth(['providers' => ['google' => [...]]]);
```

Each call replaces only the specified keys; unspecified keys keep their defaults.

## 🛡️ Testing Authentication

### Login Tests

```php
<?php
class SessionAuthenticatorTest extends AuthenticationTestCase
{
    public function testLoginSuccess(): void
    {
        $result = $this->auth->attempt([
            'email'    => 'test@example.com',
            'password' => 'secret123'
        ]);

        $this->assertTrue($result->isOK());
        $this->assertTrue($this->auth->loggedIn());
        $this->assertSame($this->user->id, $this->auth->id());
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $result = $this->auth->attempt([
            'email'    => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $this->assertFalse($result->isOK());
        $this->assertFalse($this->auth->loggedIn());
        $this->assertStringContainsString('Invalid credentials', $result->reason());
    }

    public function testLoginWithNonExistentUser(): void
    {
        $result = $this->auth->attempt([
            'email'    => 'nonexistent@example.com',
            'password' => 'secret123'
        ]);

        $this->assertFalse($result->isOK());
        $this->assertFalse($this->auth->loggedIn());
    }
}
```

### Logout Tests

```php
<?php
public function testLogout(): void
{
    // Login first
    $this->auth->attempt([
        'email'    => 'test@example.com',
        'password' => 'secret123'
    ]);

    $this->assertTrue($this->auth->loggedIn());

    // Logout
    $this->auth->logout();

    $this->assertFalse($this->auth->loggedIn());
    $this->assertNull($this->auth->user());
    $this->assertNull($this->auth->id());
}
```

### Remember Me Tests

```php
<?php
public function testRememberMeFunctionality(): void
{
    $result = $this->auth->remember()->attempt([
        'email'    => 'test@example.com',
        'password' => 'secret123'
    ]);

    $this->assertTrue($result->isOK());
    
    // Check remember token exists
    $user = $this->auth->user();
    $this->assertNotNull($user->remember_token);
    
    // Simulate new session
    session()->destroy();
    
    // Should still be logged in via remember token
    $this->assertTrue($this->auth->loggedIn());
}
```

## 👥 Testing Authorization

### Permission Tests

```php
<?php
class AuthorizationTest extends DatabaseTestCase
{
    public function testUserHasPermission(): void
    {
        $user = $this->createUserWithPermission('posts.create');
        
        $this->auth->login($user);
        
        $this->assertTrue($this->auth->user()->can('posts.create'));
        $this->assertFalse($this->auth->user()->can('posts.delete'));
    }

    public function testGroupPermissions(): void
    {
        $group = $this->createGroup('editors', ['posts.create', 'posts.edit']);
        $user  = $this->createUser();
        
        $user->addToGroup($group);
        $this->auth->login($user);

        $this->assertTrue($this->auth->user()->inGroup('editors'));
        $this->assertTrue($this->auth->user()->can('posts.create'));
        $this->assertTrue($this->auth->user()->can('posts.edit'));
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = $this->createUser();
        $user->addPermission($permission);
        
        return $user;
    }
}
```

## 🎛️ Testing Controllers

### Controller Test Example

```php
<?php
class BaseAuthControllerTest extends DatabaseTestCase
{
    private MockBaseAuthController $controller;
    private IncomingRequest $request;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request  = $this->createMockRequest();
        $this->response = new Response(new App());
        
        $this->controller = new MockBaseAuthController();
        $this->controller->initController($this->request, $this->response, service('logger'));
    }

    public function testControllerInitialization(): void
    {
        $this->assertNotNull($this->controller->publicAuthHandler);
        $this->assertInstanceOf(BaseAuthController::class, $this->controller);
    }

    public function testAjaxResponse(): void
    {
        $this->request->setHeader('X-Requested-With', 'XMLHttpRequest');
        
        $response = $this->controller->testMethod();
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertJson($response->getBody());
    }

    private function createMockRequest(): IncomingRequest
    {
        $userAgent = $this->createMock(UserAgent::class);
        
        return new IncomingRequest(
            new App(),
            new URI('http://example.com/test'),
            'php://input',
            $userAgent
        );
    }
}
```

## 🔍 Testing Filters

### Filter Test Example

```php
<?php
class AuthFilterTest extends DatabaseTestCase
{
    public function testFilterAllowsAuthenticatedUser(): void
    {
        $this->loginAsUser();
        
        $request  = service('request');
        $response = service('response');
        
        $filter = new AuthFilter();
        $result = $filter->before($request);
        
        $this->assertNull($result); // No redirect means allowed
    }

    public function testFilterRedirectsUnauthenticatedUser(): void
    {
        $request  = service('request');
        $response = service('response');
        
        $filter = new AuthFilter();
        $result = $filter->before($request);
        
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/login', $result->getHeaderLine('Location'));
    }

    public function testPermissionFilter(): void
    {
        $user = $this->createUserWithPermission('admin.access');
        $this->auth->login($user);
        
        $filter = new PermissionFilter();
        $result = $filter->before(service('request'), ['admin.access']);
        
        $this->assertNull($result); // Allowed
    }
}
```

## 📊 Testing Models

### User Model Tests

```php
<?php
class UserModelTest extends DatabaseTestCase
{
    public function testCreateUser(): void
    {
        $userData = [
            'username' => 'newuser',
            'email'    => 'new@example.com',
            'password' => 'password123'
        ];

        $userModel = model('UserModel');
        $userId = $userModel->insert($userData);

        $this->assertIsInt($userId);
        
        $user = $userModel->find($userId);
        $this->assertSame('newuser', $user->username);
        $this->assertSame('new@example.com', $user->email);
    }

    public function testPasswordHashing(): void
    {
        $user = new User([
            'username' => 'testuser',
            'email'    => 'test@example.com',
            'password' => 'plaintext'
        ]);

        $this->assertNotSame('plaintext', $user->password_hash);
        $this->assertTrue(password_verify('plaintext', $user->password_hash));
    }

    public function testEmailValidation(): void
    {
        $userModel = model('UserModel');
        
        $result = $userModel->insert([
            'username' => 'testuser',
            'email'    => 'invalid-email',
            'password' => 'password123'
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $userModel->errors());
    }
}
```

## 🏗️ Testing Traits

### Testing BaseControllerTrait

```php
<?php
class BaseControllerTraitTest extends DatabaseTestCase
{
    use BaseControllerTrait;

    public function testGetToken(): void
    {
        $token = $this->getToken();
        
        $this->assertIsArray($token);
        $this->assertArrayHasKey('name', $token);
        $this->assertArrayHasKey('hash', $token);
        $this->assertNotEmpty($token['name']);
        $this->assertNotEmpty($token['hash']);
    }

    public function testSetRequestUnauthorized(): void
    {
        $this->setRequestUnauthorized();
        
        $this->assertFalse($this->isRequestAuthorized());
    }
}
```

## 🎯 Testing Best Practices

### 1. Test Isolation
```php
<?php
// Each test should be independent
protected function setUp(): void
{
    parent::setUp();
    $this->refreshDatabase();
    $this->createFreshUser();
}
```

### 2. Clear Test Names
```php
<?php
// Good: Descriptive test names
public function testUserCannotLoginWithExpiredPassword(): void
public function testAdminCanAccessUserManagement(): void
public function testGuestIsRedirectedToLogin(): void

// Bad: Vague test names
public function testLogin(): void
public function testAccess(): void
```

### 3. Test Data Factories
```php
<?php
class UserFactory
{
    public static function create(array $overrides = []): User
    {
        return new User(array_merge([
            'username' => fake()->userName(),
            'email'    => fake()->email(),
            'password' => 'password123',
            'active'   => true,
        ], $overrides));
    }

    public static function createWithPermissions(array $permissions): User
    {
        $user = self::create();
        foreach ($permissions as $permission) {
            $user->addPermission($permission);
        }
        return $user;
    }
}
```

### 4. Mock External Dependencies
```php
<?php
public function testEmailNotificationSent(): void
{
    $emailMock = $this->createMock(EmailService::class);
    $emailMock->expects($this->once())
              ->method('send')
              ->with($this->equalTo('user@example.com'));

    Services::injectMock('email', $emailMock);
    
    // Test code that should trigger email
}
```

## 🚀 Contributing Tests

### Writing New Tests

1. **Follow naming conventions**: Use descriptive test method names
2. **Test one thing**: Each test should verify one specific behavior
3. **Use assertions properly**: Choose the most specific assertion available
4. **Clean up**: Ensure tests don't leave side effects

### Test Coverage

```bash
# Generate coverage report
composer test:coverage

# View coverage in browser
open build/coverage/html/index.html
```

### Pull Request Testing

Before submitting a PR:

```bash
# Run full test suite
composer test

# Check code style
composer cs:check

# Run static analysis
composer analyze

# Ensure no deprecation warnings
composer test -- --display-deprecations
```

### Test Examples Repository

For more test examples, check the `/tests` directory:

- `tests/Authentication/` - Authentication tests
- `tests/Authorization/` - Authorization tests  
- `tests/Controllers/` - Controller tests
- `tests/Entities/` - Entity tests
- `tests/Models/` - Model tests

## 🔗 Related Documentation

- [Quick Start](01-quick-start.md) - Getting started with Daycry Auth
- [Authentication](03-authentication.md) - Authentication system details
- [Authorization](06-authorization.md) - Permission and role system
- [Controllers](05-controllers.md) - Controller implementation
- [Filters](04-filters.md) - Security filters

---

Remember: Good tests are an investment in your application's reliability and your team's confidence. Write tests that clearly express intent, are easy to maintain, and provide valuable feedback when things break.

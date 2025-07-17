# 🎮 Controllers - Complete Guide

This complete guide will teach you everything about Daycry Auth controllers, including the refactored `BaseAuthController`.

## 📋 Index

- [🏗️ BaseAuthController](#️-baseauthcontroller)
- [🎯 Included Controllers](#-included-controllers)
- [🛠️ Creating Custom Controllers](#️-creating-custom-controllers)
- [⚡ Practical Examples](#-practical-examples)
- [🚀 Best Practices](#-best-practices)

## 🏗️ BaseAuthController

### What is BaseAuthController?

The `BaseAuthController` is a refactored base class that provides common functionality for all authentication controllers. It uses the service pattern and follows SOLID principles.

### Main Features

- ✅ **Reusable Helper Methods**: For validation, redirects and error handling
- ✅ **Separation of Concerns**: Each method has a specific function
- ✅ **Better Testability**: Small and focused methods
- ✅ **Strong Typing**: PHP 8.1+ with explicit types
- ✅ **Full Compatibility**: 100% compatible with existing code

### BaseAuthController Structure

```php
<?php

namespace Daycry\Auth\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Interfaces\AuthController;
use Daycry\Auth\Traits\BaseControllerTrait;
use Daycry\Auth\Traits\Viewable;

abstract class BaseAuthController extends BaseController implements AuthController
{
    use BaseControllerTrait;
    use Viewable;

    // Abstract method that each controller must implement
    abstract protected function getValidationRules(): array;
    
    // Available helper methods...
}
```

## 🎯 Included Controllers

### 1. LoginController

**Purpose**: Handles the login/logout process

**Main methods**:
- `loginView()`: Shows the login form
- `loginAction()`: Processes login attempt
- `logoutAction()`: Logs out the session

**Usage example**:

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\LoginController as BaseLoginController;

class CustomLoginController extends BaseLoginController
{
    // Customize login view
    public function loginView(): ResponseInterface
    {
        if ($redirect = $this->redirectIfLoggedIn()) {
            return $redirect;
        }

        // Custom view
        $data = [
            'title' => 'My Custom Login',
            'token' => $this->getTokenArray()
        ];

        $content = view('custom/login', $data);
        return $this->response->setBody($content);
    }
}
```

### 2. RegisterController

**Purpose**: Handles registration of new users

**Main methods**:
- `registerView()`: Shows the registration form
- `registerAction()`: Processes registration

**Usage example**:

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\RegisterController as BaseRegisterController;

class CustomRegisterController extends BaseRegisterController
{
    // Customize registration validation
    protected function getValidationRules(): array
    {
        return [
            'username' => [
                'label' => 'Username',
                'rules' => 'required|min_length[3]|max_length[20]|is_unique[users.username]',
            ],
            'email' => [
                'label' => 'Email',
                'rules' => 'required|valid_email|is_unique[users.email]',
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required|min_length[8]|strong_password',
            ],
            'password_confirm' => [
                'label' => 'Confirm Password',
                'rules' => 'required|matches[password]',
            ],
        ];
    }

    public function registerAction(): ResponseInterface
    {
        // Custom logic before registration
        if (!config('Auth')->allowRegistration) {
            return $this->displayError('Registration is currently disabled');
        }

        return parent::registerAction();
    }
}
```

### 3. ActionController

**Purpose**: Handles post-authentication actions (2FA, email verification, etc.)

**Main methods**:
- `show()`: Shows the action form
- `handle()`: Processes the action
- `verify()`: Verifies the action

### 4. MagicLinkController

**Purpose**: Handles magic link authentication

**Main methods**:
- `loginView()`: Shows magic link request form
- `loginAction()`: Sends magic link
- `verify()`: Verifies and logs in via magic link

## 🛠️ Creating Custom Controllers

### 1. Basic Custom Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class MyAuthController extends BaseAuthController
{
    // Required method implementation
    protected function getValidationRules(): array
    {
        return [
            'email' => [
                'label' => 'Email',
                'rules' => 'required|valid_email',
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required|min_length[8]',
            ],
        ];
    }

    public function customLogin(): ResponseInterface
    {
        // Use helper methods from BaseAuthController
        if ($redirect = $this->redirectIfLoggedIn()) {
            return $redirect;
        }

        // Process form if submitted
        if ($this->request->is('post')) {
            return $this->handleLogin();
        }

        // Show form
        return $this->view('auth/custom_login', [
            'title' => 'Custom Login',
            'token' => $this->getTokenArray()
        ]);
    }

    private function handleLogin(): ResponseInterface
    {
        // Validate input
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules())) {
            return $this->view('auth/custom_login', [
                'errors' => $this->validator->getErrors(),
                'token' => $this->getTokenArray()
            ]);
        }

        // Attempt authentication
        $credentials = $this->getCredentials();
        $result = $this->attemptAuthentication($credentials);

        if (!$result->isOK()) {
            return $this->displayError($result->reason());
        }

        // Successful login
        return $this->handleSuccessfulLogin($result->extraInfo());
    }
}
```

### 2. API Authentication Controller

```php
<?php

namespace App\Controllers\API;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\RESTful\ResourceController;

class AuthAPIController extends ResourceController
{
    protected $format = 'json';

    public function login()
    {
        $credentials = $this->request->getJSON(true);
        
        // Validate JSON input
        if (!$this->validateCredentials($credentials)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Attempt authentication
        $result = auth('access_token')->attempt($credentials);

        if (!$result->isOK()) {
            return $this->failUnauthorized($result->reason());
        }

        // Generate access token
        $user = auth('access_token')->user();
        $token = $user->generateAccessToken('api');

        return $this->respond([
            'message' => 'Login successful',
            'user' => $user->toArray(),
            'token' => $token->raw_token,
            'expires' => $token->expires,
        ]);
    }

    public function logout()
    {
        $user = auth('access_token')->user();
        
        if ($user) {
            // Revoke current token
            $user->revokeAccessToken($this->getCurrentToken());
        }

        return $this->respond(['message' => 'Logout successful']);
    }

    public function refresh()
    {
        $user = auth('access_token')->user();
        
        if (!$user) {
            return $this->failUnauthorized('Invalid token');
        }

        // Generate new token
        $newToken = $user->generateAccessToken('api');

        return $this->respond([
            'token' => $newToken->raw_token,
            'expires' => $newToken->expires,
        ]);
    }

    private function validateCredentials(array $credentials): bool
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        return $this->validate($credentials, $rules);
    }

    private function getCurrentToken(): ?string
    {
        $header = $this->request->getHeaderLine('X-API-KEY');
        return $header ?: null;
    }
}
```

### 3. Multi-Step Authentication Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class MultiStepAuthController extends BaseAuthController
{
    protected function getValidationRules(): array
    {
        return [
            'step1' => [
                'email' => 'required|valid_email',
            ],
            'step2' => [
                'password' => 'required|min_length[8]',
            ],
            'step3' => [
                'two_factor_code' => 'required|exact_length[6]|numeric',
            ],
        ];
    }

    public function step1(): ResponseInterface
    {
        if ($this->request->is('post')) {
            return $this->processStep1();
        }

        return $this->view('auth/step1', [
            'title' => 'Step 1: Email',
            'token' => $this->getTokenArray()
        ]);
    }

    public function step2(): ResponseInterface
    {
        // Verify step 1 was completed
        if (!session('auth_step1_email')) {
            return redirect()->to('auth/step1');
        }

        if ($this->request->is('post')) {
            return $this->processStep2();
        }

        return $this->view('auth/step2', [
            'title' => 'Step 2: Password',
            'email' => session('auth_step1_email'),
            'token' => $this->getTokenArray()
        ]);
    }

    public function step3(): ResponseInterface
    {
        // Verify previous steps
        if (!session('auth_step2_verified')) {
            return redirect()->to('auth/step1');
        }

        if ($this->request->is('post')) {
            return $this->processStep3();
        }

        return $this->view('auth/step3', [
            'title' => 'Step 3: Two-Factor Authentication',
            'token' => $this->getTokenArray()
        ]);
    }

    private function processStep1(): ResponseInterface
    {
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules()['step1'])) {
            return $this->view('auth/step1', [
                'errors' => $this->validator->getErrors(),
                'token' => $this->getTokenArray()
            ]);
        }

        $email = $this->request->getPost('email');
        
        // Check if user exists
        $userProvider = auth()->getProvider();
        $user = $userProvider->findByCredentials(['email' => $email]);

        if (!$user) {
            return $this->displayError('User not found');
        }

        // Store email in session for next step
        session()->set('auth_step1_email', $email);
        session()->set('auth_step1_user_id', $user->id);

        return redirect()->to('auth/step2');
    }

    private function processStep2(): ResponseInterface
    {
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules()['step2'])) {
            return $this->view('auth/step2', [
                'errors' => $this->validator->getErrors(),
                'email' => session('auth_step1_email'),
                'token' => $this->getTokenArray()
            ]);
        }

        $credentials = [
            'email' => session('auth_step1_email'),
            'password' => $this->request->getPost('password'),
        ];

        // Verify credentials but don't log in yet
        $result = auth()->check($credentials);

        if (!$result->isOK()) {
            return $this->displayError('Invalid credentials');
        }

        // Generate and send 2FA code
        $this->send2FACode(session('auth_step1_user_id'));
        
        session()->set('auth_step2_verified', true);

        return redirect()->to('auth/step3');
    }

    private function processStep3(): ResponseInterface
    {
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules()['step3'])) {
            return $this->view('auth/step3', [
                'errors' => $this->validator->getErrors(),
                'token' => $this->getTokenArray()
            ]);
        }

        $code = $this->request->getPost('two_factor_code');

        if (!$this->verify2FACode(session('auth_step1_user_id'), $code)) {
            return $this->displayError('Invalid 2FA code');
        }

        // Complete authentication
        $userProvider = auth()->getProvider();
        $user = $userProvider->findById(session('auth_step1_user_id'));
        
        auth()->login($user);

        // Clear session data
        session()->remove(['auth_step1_email', 'auth_step1_user_id', 'auth_step2_verified']);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    private function send2FACode(int $userId): void
    {
        // Implementation for sending 2FA code
        // This could be email, SMS, etc.
    }

    private function verify2FACode(int $userId, string $code): bool
    {
        // Implementation for verifying 2FA code
        // Return true if valid, false otherwise
        return true; // Placeholder
    }
}
```

## ⚡ Practical Examples

### 1. Social Login Controller

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class SocialAuthController extends BaseAuthController
{
    protected function getValidationRules(): array
    {
        return []; // No validation needed for social login
    }

    public function google(): ResponseInterface
    {
        $googleProvider = $this->getGoogleProvider();
        
        if (!$this->request->getGet('code')) {
            // Redirect to Google OAuth
            $authUrl = $googleProvider->getAuthorizationUrl();
            session()->set('oauth2state', $googleProvider->getState());
            
            return redirect()->to($authUrl);
        }

        // Handle callback
        return $this->handleGoogleCallback($googleProvider);
    }

    public function github(): ResponseInterface
    {
        $githubProvider = $this->getGithubProvider();
        
        if (!$this->request->getGet('code')) {
            $authUrl = $githubProvider->getAuthorizationUrl();
            session()->set('oauth2state', $githubProvider->getState());
            
            return redirect()->to($authUrl);
        }

        return $this->handleGithubCallback($githubProvider);
    }

    private function handleGoogleCallback($provider): ResponseInterface
    {
        try {
            // Get access token
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $this->request->getGet('code')
            ]);

            // Get user details
            $googleUser = $provider->getResourceOwner($token);
            
            // Find or create user
            $user = $this->findOrCreateUser([
                'email' => $googleUser->getEmail(),
                'username' => $googleUser->getName(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
            ]);

            // Log in user
            auth()->login($user);

            return redirect()->to(config('Auth')->loginRedirect());

        } catch (\Exception $e) {
            return $this->displayError('Google authentication failed: ' . $e->getMessage());
        }
    }

    private function findOrCreateUser(array $data): object
    {
        $userProvider = auth()->getProvider();
        
        // Try to find existing user by email
        $user = $userProvider->findByCredentials(['email' => $data['email']]);
        
        if (!$user) {
            // Create new user
            $userProvider->save([
                'email' => $data['email'],
                'username' => $data['username'],
                'active' => 1,
                'provider' => $data['provider'],
                'provider_id' => $data['provider_id'],
            ]);
            
            $user = $userProvider->findByCredentials(['email' => $data['email']]);
        }

        return $user;
    }
}
```

### 2. Admin User Management Controller

```php
<?php

namespace App\Controllers\Admin;

use Daycry\Auth\Controllers\BaseAuthController;
use CodeIgniter\HTTP\ResponseInterface;

class UserManagementController extends BaseAuthController
{
    protected $filters = [
        'session',
        'group:admin',
        'permission:users.manage'
    ];

    protected function getValidationRules(): array
    {
        return [
            'create' => [
                'email' => 'required|valid_email|is_unique[users.email]',
                'username' => 'required|min_length[3]|is_unique[users.username]',
                'password' => 'required|min_length[8]',
                'groups' => 'permit_empty|array',
            ],
            'update' => [
                'email' => 'required|valid_email',
                'username' => 'required|min_length[3]',
                'groups' => 'permit_empty|array',
            ],
        ];
    }

    public function index(): ResponseInterface
    {
        $userProvider = auth()->getProvider();
        $users = $userProvider->findAll();

        return $this->view('admin/users/index', [
            'title' => 'User Management',
            'users' => $users,
        ]);
    }

    public function create(): ResponseInterface
    {
        if ($this->request->is('post')) {
            return $this->handleCreate();
        }

        return $this->view('admin/users/create', [
            'title' => 'Create User',
            'groups' => $this->getAvailableGroups(),
            'token' => $this->getTokenArray()
        ]);
    }

    public function edit(int $id): ResponseInterface
    {
        $user = $this->getUserOr404($id);

        if ($this->request->is('post')) {
            return $this->handleUpdate($user);
        }

        return $this->view('admin/users/edit', [
            'title' => 'Edit User',
            'user' => $user,
            'groups' => $this->getAvailableGroups(),
            'userGroups' => $user->getGroups(),
            'token' => $this->getTokenArray()
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->getUserOr404($id);

        // Don't allow deleting current user
        if ($user->id === auth()->id()) {
            return $this->displayError('Cannot delete your own account');
        }

        // Delete user
        $userProvider = auth()->getProvider();
        $userProvider->delete($user->id);

        return redirect()->to('admin/users')->with('message', 'User deleted successfully');
    }

    private function handleCreate(): ResponseInterface
    {
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules()['create'])) {
            return $this->view('admin/users/create', [
                'errors' => $this->validator->getErrors(),
                'groups' => $this->getAvailableGroups(),
                'token' => $this->getTokenArray()
            ]);
        }

        $data = $this->request->getPost();
        $userProvider = auth()->getProvider();

        // Create user
        $userId = $userProvider->save($data);
        $user = $userProvider->findById($userId);

        // Assign groups
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $groupName) {
                $user->addGroup($groupName);
            }
        }

        return redirect()->to('admin/users')->with('message', 'User created successfully');
    }

    private function handleUpdate(object $user): ResponseInterface
    {
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules()['update'])) {
            return $this->view('admin/users/edit', [
                'errors' => $this->validator->getErrors(),
                'user' => $user,
                'groups' => $this->getAvailableGroups(),
                'userGroups' => $user->getGroups(),
                'token' => $this->getTokenArray()
            ]);
        }

        $data = $this->request->getPost();
        $userProvider = auth()->getProvider();

        // Update user
        $userProvider->save($data, $user->id);

        // Update groups
        $user->syncGroups($data['groups'] ?? []);

        return redirect()->to('admin/users')->with('message', 'User updated successfully');
    }

    private function getUserOr404(int $id): object
    {
        $userProvider = auth()->getProvider();
        $user = $userProvider->findById($id);

        if (!$user) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('User not found');
        }

        return $user;
    }

    private function getAvailableGroups(): array
    {
        return config('Auth')->groups;
    }
}
```

## 🚀 Best Practices

### 1. **Use BaseAuthController for Common Functionality**

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;

class MyController extends BaseAuthController
{
    // Always implement getValidationRules()
    protected function getValidationRules(): array
    {
        return [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];
    }

    // Use helper methods from base class
    public function login()
    {
        // ✅ Good: Use helper methods
        if ($redirect = $this->redirectIfLoggedIn()) {
            return $redirect;
        }

        // ✅ Good: Use validation helpers
        if (!$this->validateData($this->request->getPost(), $this->getValidationRules())) {
            return $this->showFormWithErrors();
        }

        // ✅ Good: Use authentication helpers
        $result = $this->attemptAuthentication($this->getCredentials());
        
        if (!$result->isOK()) {
            return $this->displayError($result->reason());
        }

        return $this->handleSuccessfulLogin($result->extraInfo());
    }
}
```

### 2. **Separate Concerns in Controllers**

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;

class AuthController extends BaseAuthController
{
    // ✅ Good: Separate methods for different responsibilities
    public function loginView(): ResponseInterface
    {
        return $this->showLoginForm();
    }

    public function loginAction(): ResponseInterface
    {
        return $this->processLogin();
    }

    public function logoutAction(): ResponseInterface
    {
        return $this->processLogout();
    }

    // ✅ Good: Private methods for specific logic
    private function showLoginForm(): ResponseInterface
    {
        // Form display logic
    }

    private function processLogin(): ResponseInterface
    {
        // Login processing logic
    }

    private function processLogout(): ResponseInterface
    {
        // Logout processing logic
    }
}
```

### 3. **Handle Errors Gracefully**

```php
<?php

namespace App\Controllers;

use Daycry\Auth\Controllers\BaseAuthController;

class SafeAuthController extends BaseAuthController
{
    public function login(): ResponseInterface
    {
        try {
            // Authentication logic
            $result = $this->attemptAuthentication($this->getCredentials());
            
            if (!$result->isOK()) {
                // ✅ Good: User-friendly error messages
                return $this->displayError($this->getErrorMessage($result->reason()));
            }

            return $this->handleSuccessfulLogin($result->extraInfo());

        } catch (\Exception $e) {
            // ✅ Good: Log errors but don't expose details to users
            log_message('error', 'Login error: ' . $e->getMessage());
            
            return $this->displayError('An error occurred during login. Please try again.');
        }
    }

    private function getErrorMessage(string $reason): string
    {
        $messages = [
            'invalid_credentials' => 'Invalid email or password',
            'account_locked' => 'Your account has been temporarily locked',
            'account_inactive' => 'Your account is not active',
        ];

        return $messages[$reason] ?? 'Login failed. Please try again.';
    }
}
```

### 4. **Use Proper HTTP Status Codes**

```php
<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;

class AuthAPIController extends ResourceController
{
    public function login()
    {
        $credentials = $this->request->getJSON(true);

        if (!$this->validateCredentials($credentials)) {
            // ✅ Good: 422 for validation errors
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $result = auth()->attempt($credentials);

        if (!$result->isOK()) {
            // ✅ Good: 401 for authentication failures
            return $this->failUnauthorized($result->reason());
        }

        // ✅ Good: 200 for success
        return $this->respond(['message' => 'Login successful']);
    }

    public function logout()
    {
        auth()->logout();
        
        // ✅ Good: 204 for successful logout with no content
        return $this->respondNoContent();
    }
}
```

### 5. **Implement Proper Testing**

```php
<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

class AuthControllerTest extends AuthTestCase
{
    public function testLoginWithValidCredentials()
    {
        $user = $this->createUser();

        $response = $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(auth()->loggedIn());
    }

    public function testLoginWithInvalidCredentials()
    {
        $response = $this->post('/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/auth/login');
        $response->assertSessionHas('error');
        $this->assertFalse(auth()->loggedIn());
    }
}
```

---

🔗 **Next**: [Authorization](06-authorization.md) - Learn about groups and permissions system

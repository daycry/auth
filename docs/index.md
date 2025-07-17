# Daycry Auth Documentation

Welcome to the complete documentation for **Daycry Auth**, a robust authentication and authorization library for CodeIgniter 4.

## Table of Contents

```{toctree}
:maxdepth: 2
:caption: Getting Started

01-quick-start
02-configuration
```

```{toctree}
:maxdepth: 2
:caption: Core Features

03-authentication
04-filters
05-controllers
06-authorization
```

```{toctree}
:maxdepth: 2
:caption: Advanced Topics

07-logging
08-testing
09-testing
```

## 🌟 Main Features

- **Multiple Authenticators**: Session, Access Token, JWT, Magic Link
- **Permission System**: Groups and granular permissions
- **Flexible Filters**: Configurable authentication chains
- **Rate Limiting**: Access attempts and rate control
- **Complete Logging**: Monitoring of all activities
- **Highly Customizable**: Extend or replace any component

## 🚀 Quick Start

If you're new to Daycry Auth, start with our [Quick Start Guide](01-quick-start.md) to get up and running in minutes.

### Installation

```bash
composer require daycry/auth
```

### Basic Usage

```php
// Login attempt
$result = auth()->attempt([
    'email' => 'user@example.com',
    'password' => 'password123'
]);

if ($result->isOK()) {
    return redirect()->to('/dashboard');
}
```

## 📚 Documentation Sections

### 🚀 [Quick Start Guide](01-quick-start.md)
Learn how to install and configure Daycry Auth with minimal setup. Perfect for getting started quickly.

### ⚙️ [Configuration](02-configuration.md)
Comprehensive guide to all configuration options, database customization, and authenticator setup.

### 🔐 [Authentication](03-authentication.md)
Complete guide to all available authenticators:
- Session Authenticator
- Access Token Authenticator  
- JWT Authenticator
- Magic Link Authentication

### 🛡️ [Security Filters](04-filters.md)
Learn how to protect your routes with authentication and authorization filters:
- Session filters
- Group filters
- Permission filters
- Custom filter chains

### 🎮 [Controllers](05-controllers.md)
Master the controller layer with BaseAuthController and custom implementations:
- Using BaseAuthController
- Creating custom auth controllers
- Best practices and patterns

### 👥 [Authorization](06-authorization.md)
Implement fine-grained access control with groups and permissions:
- User groups management
- Permission system
- Authorization filters
- Role-based access control

### 📊 [Logging and Monitoring](07-logging.md)
Monitor and track authentication activities:
- Login attempt logging
- Activity monitoring
- Security alerts
- Performance tracking

### 🧪 [Testing](08-testing.md)
Learn how to test your authentication system:
- Unit testing
- Integration testing
- Authentication mocking
- Test helpers

### 🧪 [Advanced Testing](09-testing.md)
Deep dive into comprehensive testing strategies:
- Performance testing
- Security testing
- CI/CD integration
- Test automation

## 🔗 Additional Resources

- **GitHub Repository**: [daycry/auth](https://github.com/daycry/auth)
- **CodeIgniter 4 Documentation**: [Official Docs](https://codeigniter4.github.io/)
- **Packagist Package**: [daycry/auth](https://packagist.org/packages/daycry/auth)

## 🆘 Need Help?

- Check our comprehensive documentation
- Review the [FAQ section](README.md#-documentation-status)
- Submit issues on [GitHub](https://github.com/daycry/auth/issues)
- Join the CodeIgniter community discussions

---

> 💡 **Tip**: Use the table of contents above to navigate to specific sections, or start with the Quick Start Guide if you're new to Daycry Auth.

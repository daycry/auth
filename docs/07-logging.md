# 📊 Logging and Monitoring

Daycry Auth provides comprehensive logging and monitoring capabilities to track authentication events and security incidents.

## 📋 Table of Contents

- [🔍 Authentication Logging](#-authentication-logging)
- [🛡️ Security Events](#️-security-events)
- [📈 Monitoring](#-monitoring)
- [⚙️ Configuration](#️-configuration)

## 🔍 Authentication Logging

### Login Events

All login attempts are logged automatically:

```php
<?php
// Successful login
[INFO] User login successful: user@example.com (ID: 123)

// Failed login
[WARNING] Failed login attempt: user@example.com from IP: 192.168.1.100
```

### Session Events

```php
<?php
// Session created
[INFO] Session created for user: user@example.com

// Session expired
[INFO] Session expired for user: user@example.com
```

## 🛡️ Security Events

### Invalid Attempts

```php
<?php
// Too many attempts
[ALERT] Too many login attempts from IP: 192.168.1.100

// Account locked
[WARNING] Account locked: user@example.com
```

### Authorization Events

```php
<?php
// Permission denied
[WARNING] Permission denied: user@example.com tried to access admin.panel

// Unauthorized access attempt
[ALERT] Unauthorized access attempt to protected resource
```

## 📈 Monitoring

### Log Analysis

Monitor authentication patterns and security events:

- Failed login attempts
- Unusual access patterns
- Permission violations
- Account lockouts

### Integration

Integration with monitoring systems:

- Application logs
- Security incident tracking
- Performance monitoring
- Audit trails

## ⚙️ Configuration

### Log Levels

Configure logging levels in your application:

```php
<?php
// In Config/Logger.php
'threshold' => [
    'auth' => 'info',
    'security' => 'warning',
]
```

### Custom Loggers

Implement custom logging for specific requirements:

```php
<?php
class CustomAuthLogger extends BaseLogger
{
    public function logAuthentication($event, $data)
    {
        // Custom logging logic
    }
}
```

---

For implementation details, see the [Authentication Guide](03-authentication.md) and [Controllers Documentation](05-controllers.md).

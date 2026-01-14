# Copilot Instructions for daycry/auth

These instructions outline the standards, conventions, and context required when working on the `daycry/auth` library for CodeIgniter 4.

## 🚀 Workflow & Quality Standards

**CRITICAL RULE**: ALL code modifications require a strict 3-step process:
1.  **Analysis**: deeply understand the current implementation and potential impact of changes.
2.  **Validation**: Verify the approach.
3.  **Implementation**: precise coding following the standards below.

*   **Strict Typing**: All files must start with `declare(strict_types=1);`.
*   **Documentation**: All classes and methods must have DocBlocks verifying types and behavior.
*   **Static Analysis**: Code must be compatible with PHPStan/Vimeo Psalm levels configured in the project.

## 📚 Library Context & Knowledge Base (MCP)

This project is a comprehensive Authentication/Authorization library for CodeIgniter 4.
**Source of Truth**: The full documentation is located in the [`docs/`](docs/) directory.

### 1. Configuration (`src/Config/Auth.php`)
*   Configuration is centralized in the `Auth` config file.
*   **Authenticators**: Supports Session, AccessToken (API), JWT, Guest.
*   **Tables**: Database table names are configurable via `$tables` array.
*   **Registration**: Controlled via `$allowRegistration`, `$defaultGroup`.

### 2. Authentication Methods (`src/Authentication`)
*   **Session**: Standard web authentication.
    *   Helper: `auth('session')`
*   **Access Tokens**: API keys (Header `X-API-KEY`).
    *   Generation: `$user->generateAccessToken('name')`
    *   Entities: `Daycry\Auth\Entities\AccessToken`
*   **JWT**: JSON Web Tokens (Header `Authorization: Bearer ...`).
    *   Helper: `auth('jwt')`
    *   Uses `Daycry\Auth\Authentication\Authenticators\JWT`

### 3. Authorization (`src/Authorization`)
*   **RBAC**: Role-Based Access Control via Groups and Permissions.
*   **Traits**: `Daycry\Auth\Authorization\Authorizable` trait on User entity handles checks.
*   **Usage**:
    *   `$user->inGroup('admin')`
    *   `$user->can('resource.action')`

### 4. Route Protection (`src/Filters`)
Route filters are the primary mechanism for securing endpoints.
*   **Authentication Filters**:
    *   `session`: Validates session login.
    *   `tokens`: Validates API token.
    *   `jwt`: Validates JWT.
    *   `chain`: Attempts configured authenticators in sequence.
*   **Authorization Filters**:
    *   `group:admin,editor`: Requires membership in listed groups.
    *   `permission:users.edit`: Requires specific permission.

### 5. Services & Helpers
*   **Service**: `CodeIgniter\Config\Services::auth()` or `auth()` helper.
*   **Helpers**:
    *   `checkEndpoint`: logic for dynamic endpoint validation.
    *   `checkIp`: logic for IP allowlisting/blocklisting.

### 6. Logging
*   Logic resides in `c:\laragon\github\auth\src\Libraries\Logger.php`.
*   Can be enabled/disabled via `Auth.enableLogs`.
*   Logs are stored in the database if configured.

## 🛠 Coding Conventions

*   **Namespace**: `Daycry\Auth`
*   **Dependencies**: Rely on `CodeIgniter\Config\Services` for accessing CI4 core services.
*   **Entities**: Use strong entity classes (`Daycry\Auth\Entities\*`) rather than raw arrays.
*   **Models**: Interact with the database via Models in `Daycry\Auth\Models\*`.

## ⚠️ Important Considerations
*   **Discovery**: The `enableDiscovery` feature scans routes/endpoints. If disabled, `checkEndpoint` short-circuits to avoid DB calls.
*   **Testing**: Changes should be verified against the extensive test suite in `tests/`.

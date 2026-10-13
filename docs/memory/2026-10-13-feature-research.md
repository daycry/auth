# Memory — Feature gap research (2026-10-13)

> Internal note (excluded from the published docs site). Read-only research
> produced on branch `chore/deps-sync`; every "absent/partial" claim was
> checked with grep at that commit. Re-verify before building — the lib evolves.

# daycry/auth: investigación de huecos funcionales (2026-10-13, rama `chore/deps-sync`)

## 1. Resumen ejecutivo

- **Lo primero: endpoints self-service y notificaciones de seguridad.** Siguen sin existir. El docblock de `UserSecurityController` los anuncia, pero ninguno está implementado. El diseño de 2026-07-18 sigue siendo válido y además desbloquea el borrado RGPD self-service.
- **Seguridad barata: aplicar `rates` a las rutas de auth por defecto.** `Auth::routes()` no aplica ningún filtro. Conviene añadir de paso agrupación por prefijo IPv6 /64, como hace django-allauth 65.17.
- **La enforcement de MFA no existe.** Los Actions 2FA se saltan sin avisar si el usuario no tiene factor registrado. Se puede hacer con un filtro más una política en `AuthSecurity`, sin capas nuevas.
- **Detección de reuso de refresh tokens.** Hay rotación, pero un replay solo devuelve un 401 genérico. RFC 9700 §4.14 la recomienda, y el coste es S/M.
- **Nuevo, alineado con RFC 9700: PKCE S256 y `iss`/nonce en el cliente OAuth.** Hoy no se usan. Es de coste S.
- **UX de passkeys 2026: conditional UI, conditional create y Signal API.** Ninguna está. Es la palanca de adopción documentada (caso adidas: +8 % de passkeys creadas).
- **Corrección a la lista previa: `passwordMaxAge` NO debe activarse por defecto.** NIST SP 800-63B-4 §3.1.1.2 prohíbe exigir cambios periódicos (SHALL NOT). Endurecer los defaults sí, pero con otro alcance (ver §5).
- **Lo enterprise (SAML/OIDC-SP, orgs, SCIM/LDAP) va en la Ola 3.** Su coste es L y depende de la demanda real.

## 2. Huecos re-verificados

| Feature | Estado (evidencia) | Valor | Coste | Riesgo |
|---|---|---|---|---|
| Endpoints self-service (changePassword, changeEmail, confirmEmailChange, unlinkOauth, deleteAccount) | **Ausente**: anunciados en `src/Controllers/UserSecurityController.php:39-43`, pero los únicos métodos son index, revokeSession, totp\*, loginActivity y confirmPassword\* (líneas 56-246). Diseño en `docs/superpowers/specs/2026-07-18-…` (untracked) | Alto | M | Bajo |
| Política de MFA obligatoria | **Ausente**: no hay ninguna flag (grep `mfaRequired\|require2fa` vacío). `src/Config/AuthSecurity.php:468-469` habla de un "not-yet-implemented axis" | Alto | M | Medio (lockout de usuarios sin factor) |
| Emails de notificación de seguridad | **Parcial**: `Session.php:573-600` solo lanza el evento `suspicious-login`. `Views/Email/suspicious_login_alert.php` no se referencia desde `src/` (solo desde docs/07, docs/13). Tampoco hay eventos para 2FA desactivado ni para contraseña o email cambiados (solo `AuditLogger.php:47-48`) | Alto | M | Bajo |
| Rate limit por defecto en las rutas de auth | **Ausente**: `src/Auth.php:170-173` solo pasa `['as'=>…]`. `Config/Auth.php:402-576` no tiene filtros. `enableInvalidAttempts=false` (`AuthSecurity.php:173`) | Alto | S | Bajo |
| SSO SAML / OIDC-SP, multi-tenant, SCIM/LDAP | **Ausente**: grep `saml\|ldap\|scim\|tenant` sin resultados, salvo el `tenant` de Azure en `AuthOAuth.php:60` | Medio | L | Alto |
| Detección de reuso y familia de refresh tokens | **Parcial**: hay rotación en `JwtController.php:108-109`, pero `UserIdentityModel.php:465` filtra `revoked_at`, así que un replay da 401 y la familia no se invalida | Alto | S/M | Bajo |
| Step-up con factor fuerte | **Parcial**: `UserSecurityController.php:246-262` solo verifica contraseña, lo que rompe a usuarios OAuth y passwordless | Medio | M | Bajo |
| JSON/negociación en flujos de formulario | **Parcial**: `BaseControllerTrait.php:163` solo devuelve JSON si la respuesta es array y la petición AJAX. `LoginController::loginAction` siempre redirige (`:46,67`) | Medio | M | Medio (BC de respuestas) |
| Spec OpenAPI | **Ausente**: no hay ningún fichero `*openapi*` | Medio | S/M | Bajo |
| Email asíncrono / en cola | **Ausente**, y `daycry/jobs` ya solo está en `suggest` (`composer.json:30-31`). Hay que diseñarlo como opcional | Medio | M | Bajo |
| Servicio de email inyectable | **Ausente**: `new TokenEmailSender()` en `MagicLinkController.php:117,140` y `PasswordResetController.php:80` | Medio | S | Bajo |
| Introspección de tokens (RFC 7662) | **Ausente** (grep `introspect` vacío) | Bajo/Medio | S | Bajo |
| Retención y purga de audit/login-history | **Ausente**: `PurgeCommand.php:29-35` solo purga remember-me y device sessions | Medio | S | Bajo |
| Export/borrado RGPD self-service | **Parcial**: solo por CLI (`GdprCommand.php:101,152`) | Medio | M (llega con self-service) | Bajo |
| Registro de consentimiento / términos | **Ausente** | Medio | M | Bajo |
| Webhooks salientes | **Ausente** | Bajo | M | Medio |
| Anti-enumeración en WebAuthn `loginOptions` | **Presente, el bug**: `WebAuthnManager.php:146-150` devuelve `allowCredentials` no vacío solo para emails que existen, lo que es un oráculo | Medio | S | Bajo |
| Hook de CAPTCHA | **Ausente** | Medio | S | Bajo |
| IdP propio (OIDC provider) | **Ausente** | Bajo | L | Alto |
| Audit log tamper-evident | **Ausente**: `AuditLogger.php` no encadena hashes | Bajo | M | Bajo |
| 2FA por SMS | **Ausente**: solo se menciona en `ActionInterface.php:25` | Bajo (NIST lo considera RESTRICTED) | M | Medio |
| Defaults endurecidos | **Sigue igual**: `PwnedValidator` comentado (`AuthSecurity.php:81`), `passwordHistorySize=0` (`:420`), `passwordMaxAge=0` (`:432`), `minimumPasswordLength=8` (`:64`), Actions `login`/`register` a null (`Auth.php:224-225`) | Medio | S | Medio (BC) |

**Hallazgo extra (hardening).** `JwtController::refresh` (`:111-121`) emite tokens sin comprobar `isBanned()`. Además, `ban()` y `PasswordChangeRecorder::record()` solo suben `token_version` (`Bannable.php:44-45`, `PasswordChangeRecorder.php:54`) y no revocan los refresh tokens. El access token resultante lo sigue rechazando `Base.php:99`, pero la familia de refresh debería quedar revocada.

## 3. Nuevas propuestas

Todas verificadas como ausentes con grep.

| Feature | Qué es | Quién lo tiene | Encaje en daycry/auth | Coste | Prio |
|---|---|---|---|---|---|
| PKCE S256 en el cliente OAuth + `iss` (RFC 9207) + nonce OIDC | Defensa contra inyección de código y mix-up. Hoy `OauthManager.php:177` llama a `getAuthorizationUrl()` sin opciones, y no hay nonce ni `iss` | RFC 9700 §2.1.1 y §4.4.2; league/oauth2-client ya trae `PKCE_METHOD_S256` | `OauthManager`, más una flag por proveedor en `AuthOAuth` | S | Alta |
| Passkeys: conditional UI (autofill), conditional create (upgrade automático) y Signal API | `mediation:'conditional'` en `get`/`create`, más `signalUnknownCredential` y `signalAllAcceptedCredentials`. Hoy `_webauthn_js.php:72-82` no usa mediation | web.dev (caso adidas), Better Auth passkey | `_webauthn_js.php`, `WebAuthnController` (endpoint de lista de credenciales), `AuthSecurity.webauthnAutoUpgrade` | S/M | Alta |
| Firma HMAC-SHA256 de peticiones | Autenticador de API firmado con secreto compartido | CodeIgniter Shield (`hmac`) | Nuevo `Authenticators\HMAC` extends `StatelessAuthenticator`, `IdentityType::HMAC`, un filtro | M | Media |
| Timeouts de sesión por inactividad y absolutos | NIST AAL2: inactividad ≤1 h y total ≤24 h, con reautenticación periódica. `sessionConfig` (`Auth.php:157-163`) no lo contempla | NIST SP 800-63B-4 §2.2; ASVS 5.0 V7 | `Session` authenticator con `last_activity` en la sesión y dos claves nuevas en `sessionConfig` | S | Alta |
| Perfil "NIST-4" de contraseñas | Mínimo 15 caracteres si la contraseña es el único factor y 8 si hay MFA. Blocklist obligatoria al crear o cambiar. Sin rotación periódica | NIST 800-63B-4 §3.1.1.2 | `CompositionValidator` con longitud condicionada a MFA. Un preset en `AuthSecurity` | S | Media |
| Rate limit con agregación IPv6 /64 y clave usuario+IP | Evita que se esquive el límite rotando IPv6. Hoy `RatesFilter.php:142` usa la IP completa | django-allauth 65.17; Fortify `EnsureLoginIsNotThrottled` (usuario+IP) | `RatesFilter`, `AttemptHandler` | S | Alta (va con el rate limit por defecto) |
| Impersonation por administrador | "Login as" auditado, con banner y vuelta atrás. Solo aparece en un comentario de `Gate.php:95` | Better Auth admin, Laravel (paquetes), Keycloak | `Session` (stash del id original), `Admin\UsersController`, `AuditLogger`, `permission:users.impersonate` | M | Media |
| Sender-constrained tokens (DPoP, RFC 9449) | Ata el JWT a una clave del cliente | RFC 9700 §2.2.1; Keycloak, Auth0 | `JWT` authenticator con validación de la prueba `DPoP` y `cnf.jkt` en el payload | M/L | Baja |
| OIDC Back-Channel / RP-initiated logout | Cerrar la sesión local cuando el IdP lo notifica | OpenID Back-Channel Logout 1.0; django-allauth 65.13 (RP-initiated) | `OauthController::backchannelLogout`, `DeviceSessionRecorder` | M | Baja/Media |
| Device Bound Session Credentials | Cookie de sesión atada a una clave en el TPM | Chrome 146 (GA en Windows desde 2026) | `Session` más endpoints de registro y refresh DBSC | L | Baja (experimental) |

## 4. Roadmap recomendado

**Ola 1: quick wins y seguridad**
1. `rates` por defecto en login, register, magic-link, jwt y reset, con IPv6 /64. Es la defensa más barata contra credential stuffing.
2. PKCE, `iss` y nonce en OAuth. Es el cumplimiento de RFC 9700 a coste S.
3. Revocar la familia de refresh tokens en un replay, `ban()` y cambio de contraseña, y comprobar el baneo en `refresh`. Cierra la ventana de tokens robados.
4. Anti-enumeración en `loginOptions`: `allowCredentials` siempre vacío, o descriptores falsos deterministas. Elimina el oráculo.
5. Timeouts de inactividad y absolutos de sesión. Son baseline NIST AAL2 y ASVS V7.
6. `auth:purge --logins/--audit` con retención configurable. Es minimización de datos RGPD.

**Ola 2: paridad con Fortify y Shield**
1. Endpoints self-service con `SecurityNotifier`, según el diseño existente. Es el mayor hueco frente a Fortify.
2. Política de MFA obligatoria (`AuthSecurity.$mfaRequired`, por grupo, con periodo de gracia). Es estándar en Clerk, Auth0 y Keycloak.
3. Step-up con el factor más fuerte disponible (passkey o TOTP). Arregla el sudo mode para cuentas sin contraseña.
4. Respuestas JSON/XHR en los flujos de formulario (códigos 200/201/422 como Fortify) y spec OpenAPI. Habilita SPAs y uso headless.
5. Conditional UI, conditional create y Signal API para passkeys. Es la mayor palanca de adopción de passkeys.
6. `TokenEmailSender` como servicio y cola opcional si `daycry/jobs` está instalado. Testabilidad y latencia.
7. Autenticador HMAC. Paridad directa con Shield.
8. Hook de CAPTCHA y registro de consentimiento/términos. Requisitos habituales de producto.

**Ola 3: enterprise**
1. Multi-tenancy y organizaciones, con RBAC por tenant. Es prerrequisito de SSO B2B.
2. SSO SAML y OIDC por organización. Lo exige el mercado B2B.
3. Provisioning SCIM y LDAP. Gestión del ciclo de vida corporativo.
4. Impersonation, audit tamper-evident, webhooks, introspección RFC 7662, DPoP y back-channel logout. Para despliegues regulados o de API.
5. Evaluar un IdP propio u OIDC provider solo si hay demanda real. Es coste L y mucha superficie de ataque.

## 5. Riesgos y decisiones abiertas

- **Defaults (BC).** Activar `rates` en `Auth::routes()` cambia el comportamiento de quien ya usa la librería. Propuesta: un opt-out con `$routeFilters` en `Config\Auth` y anunciarlo en una minor con nota en `docs/12-migration.md`. Hay que decidir si `PwnedValidator` se activa por defecto: depende de la red, ya tiene timeouts (`AuthSecurity.php:390-392`) y necesita una política de fallo abierto o cerrado.
- **`passwordMaxAge`.** Debe quedarse en 0 (NIST lo prohíbe). En su lugar hay que forzar el cambio ante evidencia de compromiso; ya existen `recheckPwnedOnLogin` y `force-reset`.
- **Longitud mínima 15.** Romper el default de 8 afecta al registro existente. Mejor ofrecerlo como preset opcional, o condicionarlo a que el usuario no tenga MFA.
- **Lockout por usuario (5 intentos, 1 h).** Facilita la denegación de servicio dirigida. NIST permite hasta 100 intentos. Valorar backoff progresivo frente a bloqueo duro.
- **Respuestas JSON.** Cambiar las firmas `RedirectResponse` a `ResponseInterface` en `LoginController` y otros es un BC para quien hereda de esos controladores.
- **MFA obligatoria.** Hay que definir la ruta de alta forzosa, cómo se recuperan los usuarios sin factor y la exención de cuentas de servicio.
- **Dependencias nuevas.** SAML (p. ej. `onelogin/php-saml`) y LDAP (`ext-ldap`) deberían ir en `suggest`, nunca en `require`. La cola se integra solo con `class_exists`.
- **Capas de deptrac.** Organizaciones y tenants obligan a decidir si el RBAC global se amplía con `tenant_id`, lo que supone migraciones e índices nuevos, o si se crea un módulo aparte.

## 6. Fuentes

- NIST SP 800-63B-4: https://pages.nist.gov/800-63-4/sp800-63b.html
- RFC 9700 (OAuth 2.0 Security BCP): https://www.rfc-editor.org/rfc/rfc9700.html
- RFC 9449 (DPoP): https://www.rfc-editor.org/rfc/rfc9449.html · RFC 9207: https://www.rfc-editor.org/rfc/rfc9207.html · RFC 7662: https://www.rfc-editor.org/rfc/rfc7662.html
- OpenID Back-Channel Logout 1.0: https://openid.net/specs/openid-connect-backchannel-1_0.html
- OWASP ASVS 5.0 (índice): https://cheatsheetseries.owasp.org/IndexASVS.html · https://cornucopia.owasp.org/taxonomy/asvs-5.0/10-oauth-and-oidc/01-generic-oauth-and-oidc-security
- Laravel Fortify: https://laravel.com/docs/12.x/fortify
- CodeIgniter Shield (filtros `auth-rates` y `hmac`): https://shield.codeigniter.com/references/controller_filters/
- django-allauth (release notes 2025 y 2026): https://docs.allauth.org/en/dev/release-notes/recent.html · https://docs.allauth.org/en/dev/release-notes/2025.html
- Better Auth (plugins y SSO SAML/OIDC v1.3): https://better-auth.com/docs/plugins · https://better-auth.com/blog/1-3
- Passkeys (conditional create y Signal API): https://web.dev/case-studies/adidas-passkeys · https://www.authsignal.com/blog/articles/the-passkey-ux-patterns-that-drive-adoption-in-2026
- DBSC: https://developer.chrome.com/blog/dbsc-origin-trial-update · https://workspaceupdates.googleblog.com/2026/05/prevent-account-takeovers-with-DBSC-now-generally-available-in-the-Chrome-browser-for-Windows.html

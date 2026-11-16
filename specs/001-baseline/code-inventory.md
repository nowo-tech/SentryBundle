# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/sentry-bundle`  
**Last audited**: 2026-11-16 (v1.12/1.13 user context ports, `UserRef`, `ErrorReporterInterface`)

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. PHPUnit under `tests/` is out of scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoSentryBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree + YAML generator | FR-CFG-001, FR-CFG-003 |
| `DependencyInjection/NowoSentryExtension.php` | DI extension, listener/middleware registration, `before_send` prepend | FR-CFG-002, FR-CFG-004, FR-DI-002 |
| `EventListener/SentryRequestListener.php` | Request/user scope enrichment | FR-LIST-001 |
| `EventListener/SubRequestAccessDeniedContextListener.php` | Sub-request 403 parent-page context | FR-LIST-004 |
| `EventListener/SentryUptimeBotListener.php` | Uptime probe handling | FR-LIST-003 |
| `Service/SentryErrorReporter.php` | Programmatic capture API | FR-SVC-001 |
| `Sentry/BeforeSendHandler.php` | Pre-send filtering (403 + SQL dedup) | FR-SENTRY-001, FR-SENTRY-003 |
| `Sentry/AccessDeniedExceptionHelper.php` | Access-denied detection helpers | FR-SENTRY-002 |
| `Doctrine/DBAL/SqlExceptionHelper.php` | SQL exception detection | FR-DBAL-001 |
| `Doctrine/DBAL/ReportedSqlExceptionRegistry.php` | WeakMap-backed reported-exception registry (safe without `kernel.reset`) | FR-DBAL-002 |
| `Doctrine/DBAL/SqlExceptionReporter.php` | SQL → Sentry capture orchestration | FR-DBAL-003 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionMiddleware.php` | DBAL middleware entry point | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionDriver.php` | Driver wrapper | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionConnection.php` | Connection wrapper (`query`, `exec`, `prepare`) | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionStatement.php` | Statement `execute` wrapper | FR-DBAL-004 |
| `DependencyInjection/Compiler/BeforeSendChainPass.php` | Chains bundle + app `before_send` | FR-CFG-004, FR-SENTRY-004 |
| `DependencyInjection/Compiler/BeforeSendTransactionChainPass.php` | Chains bundle + app `before_send_transaction` | FR-SENTRY-004, FR-SENTRY-005 |
| `Sentry/BeforeSendChain.php` | Primary/next callable chain | FR-SENTRY-004 |
| `Sentry/BeforeSendTransactionHandler.php` | Transaction trimming (spans, breadcrumbs) | FR-SENTRY-005 |
| `Sentry/EventPayloadTrimmer.php` | Large array/string truncation | FR-SENTRY-005 |
| `Service/ErrorReporterInterface.php` | Port of the safe error reporter (1.13) | FR-SVC-001 |
| `DependencyInjection/Compiler/UserContextProvidersPass.php` | Wires the request listener to the user context provider the host chose | FR-USER-001, FR-DI-001 |
| `User/UserContextProviderInterface.php` | Framework-free user context port (1.13) | FR-USER-001 |
| `User/SentryUserContextProviderInterface.php` | Deprecated 1.12 user context port (removed in 2.0) | FR-USER-001 |
| `User/DefaultSentryUserContextProvider.php` | Default provider: `getId()` or HMAC of the identifier unless identifier opt-in | FR-USER-001 |
| `User/UserRef.php` | Framework-free reference to the authenticated user (1.13) | FR-USER-001 |
| `User/UserRefFactory.php` | Builds `UserRef` from a security user | FR-USER-001 |
| `Internal/HiddenValues.php` | Keeps the user identifier / object of `UserRef` out of dumps | FR-USER-001 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Core listener + service wiring | FR-DI-001 |
| `Resources/config/doctrine_dbal.yaml` | DBAL middleware + SQL reporter services | FR-DI-002 |

## Removed from production (historical)

| Former file | Replaced by |
| --- | --- |
| `EventListener/IgnoreAccessDeniedSentryListener.php` | `Sentry/BeforeSendHandler.php` + BC config `ignore_access_denied_listener` (removed in 1.5.0) |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes (`src/**/*.php`) | 29 | 29 |
| Symfony config (`src/Resources/config/`) | 2 | 2 |
| **Total production sources** | **31** | **31** |

## Demo traceability (illustrative, not Packagist contract)

| Demo artifact | Spec reference |
| --- | --- |
| `demo/*/src/Controller/SentryDemoController.php` routes `sql_caught`, `sql_uncaught` | US-07, SC-004 |
| `demo/*/config/packages/doctrine.yaml` | US-07 |
| `demo/*/config/packages/nowo_sentry.yaml` `dbal_exception_reporter` | FR-CFG-001 |
| `demo/*/tests/Controller/SentryDemoControllerTest.php` | SC-004 |

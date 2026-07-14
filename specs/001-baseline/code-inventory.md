# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/sentry-bundle`  
**Last audited**: 2026-07-14

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
| `Doctrine/DBAL/ReportedSqlExceptionRegistry.php` | Per-request reported-exception registry | FR-DBAL-002 |
| `Doctrine/DBAL/SqlExceptionReporter.php` | SQL → Sentry capture orchestration | FR-DBAL-003 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionMiddleware.php` | DBAL middleware entry point | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionDriver.php` | Driver wrapper | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionConnection.php` | Connection wrapper (`query`, `exec`, `prepare`) | FR-DBAL-004 |
| `Doctrine/DBAL/Middleware/SentryDbalExceptionStatement.php` | Statement `execute` wrapper | FR-DBAL-004 |

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
| PHP classes | 16 | 16 |
| YAML config | 2 | 2 |
| **Total `src/` artifacts** | **18** | **18** |

## Demo traceability (illustrative, not Packagist contract)

| Demo artifact | Spec reference |
| --- | --- |
| `demo/*/src/Controller/SentryDemoController.php` routes `sql_caught`, `sql_uncaught` | US-07, SC-004 |
| `demo/*/config/packages/doctrine.yaml` | US-07 |
| `demo/*/config/packages/nowo_sentry.yaml` `dbal_exception_reporter` | FR-CFG-001 |
| `demo/*/tests/Controller/SentryDemoControllerTest.php` | SC-004 |

# Feature Specification: SentryBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Last updated**: 2026-07-14  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/sentry-bundle`  
**Configuration root**: `nowo_sentry`

Companion bundle for `sentry/sentry-symfony` with configurable event listeners: enriched request/user context, filtered access-denied noise (via `before_send`), sub-request 403 context enrichment, uptime-bot short-circuit, safe programmatic error reporting, and optional Doctrine DBAL SQL exception capture (including errors swallowed by application `catch` blocks).

---

## User Scenarios & Testing

See user stories US-01…US-07 in [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md).

### User Story 1 — Enriched error context (Priority: P1)

**Given** an authenticated request and `request_listener.enabled=true`, **When** `kernel.request` fires on the main request, **Then** Sentry scope receives domain/environment tags and optional user + session id.

### User Story 2 — Suppress expected 403s (Priority: P1)

**Given** a pure `AccessDeniedException` or `AccessDeniedHttpException` (main or sub-request), **When** Sentry is about to send the event, **Then** `BeforeSendHandler` drops it when `before_send_handler.ignore_pure_access_denied=true` (BC toggle: `ignore_access_denied_listener.enabled`).

### User Story 3 — Uptime probes (Priority: P2)

**Given** a request from a configured uptime user-agent to a listed path, **When** `SentryUptimeBotListener` handles it, **Then** synthetic OK response is returned without polluting error metrics.

### User Story 4 — Safe programmatic reporting (Priority: P2)

**Given** application code calls `SentryErrorReporter::captureException()`, **When** Sentry hub is missing or misconfigured, **Then** the call returns false and never breaks the request flow.

### User Story 5 — Sub-request 403 breaks parent page (Priority: P1)

**Given** a sub-request throws access denied and the parent page fails with a non-403 outer exception (e.g. Twig rendering error), **When** `SubRequestAccessDeniedContextListener` runs on the main request, **Then** Sentry scope is enriched with sub-request context and the event is **not** dropped by `BeforeSendHandler`.

### User Story 6 — SQL exceptions in caught code (Priority: P1)

**Given** `dbal_exception_reporter.enabled=true` and Doctrine DBAL is installed, **When** a SQL/driver exception occurs during query execution (even if later caught in application code), **Then** the middleware reports it to Sentry with SQL and connection context, rethrows the exception, and deduplicates if the same error is also captured by the Sentry SDK listener.

### User Story 7 — Demo verification (Priority: P3)

**Given** a demo app under `demo/`, **When** a developer opens `/sentry`, **Then** routes exist for SQL caught/uncaught scenarios and other bundle use cases documented in [`demo/README.md`](../../demo/README.md).

---

## Requirements

### Bundle & configuration

- **FR-BUNDLE-001**: `NowoSentryBundle` MUST complement (not replace) `sentry/sentry-symfony`; alias `nowo_sentry`.
- **FR-CFG-001**: Config tree MUST define disable-able nodes: `request_listener`, `ignore_access_denied_listener`, `before_send_handler`, `sub_request_access_denied_listener`, `uptime_bot_listener`, `error_reporter`, `dbal_exception_reporter` with documented sub-keys.
- **FR-CFG-002**: Extension MUST load `services.yaml`, inject per-feature config arrays and `%kernel.environment%`; conditionally load `doctrine_dbal.yaml` when Doctrine DBAL middleware is available.
- **FR-CFG-003**: `Configuration::generateConfigFile()` MUST write documented default YAML when `symfony/yaml` is available (used by install flows/tests).
- **FR-CFG-004**: Extension MUST prepend `sentry.options.before_send` with `nowo_sentry.before_send_handler` when `before_send_handler.register_automatically=true` and the app did not configure `before_send`.
- **FR-DI-001**: Listeners and `SentryErrorReporter` MUST be tagged with configurable priorities; hub and logger autowired when available.
- **FR-DI-002**: When `dbal_exception_reporter.enabled=true` and Doctrine Bundle is present, `SentryDbalExceptionMiddleware` MUST register with tag `doctrine.middleware` (all connections or configured subset).

### Request & uptime listeners

- **FR-LIST-001**: `SentryRequestListener` MUST run on main request only; respect `enabled` and per-flag toggles; swallow Sentry/Redis/session errors silently.
- **FR-LIST-003**: `SentryUptimeBotListener` MUST match configurable user-agent prefixes and paths; return early OK for probes when enabled.

### Access denied handling

- **FR-SENTRY-001**: `BeforeSendHandler` MUST drop pure access-denied exceptions when enabled; MUST keep parent-page failures whose outer exception is not access denied but whose chain contains one.
- **FR-SENTRY-002**: `AccessDeniedExceptionHelper` MUST provide shared detection helpers for access-denied types and exception chains.
- **FR-LIST-004**: `SubRequestAccessDeniedContextListener` MUST enrich Sentry scope on main request when a sub-request access denied breaks the parent page; MUST NOT drop events.

### Doctrine DBAL SQL reporting

- **FR-DBAL-001**: `SqlExceptionHelper` MUST detect Doctrine driver and DBAL SQL exceptions.
- **FR-DBAL-002**: `ReportedSqlExceptionRegistry` MUST track reported exceptions per request/worker cycle and reset via `kernel.reset`.
- **FR-DBAL-003**: `SqlExceptionReporter` MUST capture SQL exceptions via `SentryErrorReporter` with SQL, connection, and SQLSTATE context; honor optional `sql_states` filter; never throw.
- **FR-DBAL-004**: DBAL driver middleware stack (`SentryDbalExceptionMiddleware` → driver → connection → statement) MUST report on failed `query`, `exec`, and prepared `execute`, then rethrow.
- **FR-SENTRY-003**: `BeforeSendHandler` MUST deduplicate events when the exception (or its previous chain) was already reported by the DBAL middleware and `deduplicate_sql_exceptions` is enabled.

### Service

- **FR-SVC-001**: `SentryErrorReporter` MUST expose safe `captureException`, `captureMessage`, breadcrumb helpers; never throw; optional PSR-3 fallback logging on failure.

---

## Edge Cases

- Sentry hub null or package absent: all listeners/reporter no-op without exception.
- Session unavailable (Redis failure): request listener continues without session extra.
- Sub-requests: request listener skips non-main requests; sub-request access-denied context listener runs only on main request.
- Doctrine DBAL or Doctrine Bundle absent: `dbal_exception_reporter` services and middleware MUST NOT be registered.
- `error_reporter.enabled=false`: public `SentryErrorReporter` / alias MUST be unavailable to the app; DBAL SQL reporter remains controlled only by `dbal_exception_reporter.enabled`.
- Uncaught SQL error: middleware reports once; SDK listener event deduplicated via `BeforeSendHandler`.
- FrankenPHP worker mode: `ReportedSqlExceptionRegistry` MUST reset between requests via `kernel.reset`.

---

## Success Criteria

- **SC-001**: **18/18** production artifacts under `src/` mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: 100% PHPUnit line coverage on `src/` (project standard).
- **SC-003**: Per-listener and per-feature disable flags verified in unit/integration tests.
- **SC-004**: Demo routes `/sentry/sql-caught` and `/sentry/sql-uncaught` demonstrate FR-DBAL-003/004 in all demo projects.

---

## Validation

`composer qa`, `make test-coverage-100` when configured, demo functional tests under `demo/*/tests/Controller/`.

---

## Out of scope

- Sentry DSN configuration (owned by `sentry/sentry-symfony`).
- Performance monitoring sampling rules beyond scope enrichment.
- ORM entity mapping or migrations (demos may use DBAL-only SQLite for SQL demos).

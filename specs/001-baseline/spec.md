# Feature Specification: SentryBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/sentry-bundle`  
**Configuration root**: `nowo_sentry`

Companion bundle for `sentry/sentry-symfony` with configurable event listeners: enriched request/user context, filtered access-denied noise, uptime-bot short-circuit, and a safe programmatic error reporter service.

---

## User Scenarios & Testing

See user stories US-01…US-05 in [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md).

### User Story 1 — Enriched error context (Priority: P1)

**Given** an authenticated request and `request_listener.enabled=true`, **When** `kernel.request` fires on the main request, **Then** Sentry scope receives domain/environment tags and optional user + session id.

### User Story 2 — Suppress expected 403s (Priority: P1)

**Given** `AccessDeniedException` is thrown, **When** `IgnoreAccessDeniedSentryListener` runs before Sentry capture, **Then** the event is discarded and not reported.

### User Story 3 — Uptime probes (Priority: P2)

**Given** a request from a configured uptime user-agent to a listed path, **When** `SentryUptimeBotListener` handles it, **Then** synthetic OK response is returned without polluting error metrics.

### User Story 4 — Safe programmatic reporting (Priority: P2)

**Given** application code calls `SentryErrorReporter::captureException()`, **When** Sentry hub is missing or misconfigured, **Then** the call returns false and never breaks the request flow.

---

## Requirements

### Bundle & configuration

- **FR-BUNDLE-001**: `NowoSentryBundle` MUST complement (not replace) `sentry/sentry-symfony`; alias `nowo_sentry`.
- **FR-CFG-001**: Config tree MUST define disable-able nodes: `request_listener`, `ignore_access_denied_listener`, `uptime_bot_listener`, `error_reporter` with documented sub-keys (tags, user info, session id, priorities, user-agents, paths).
- **FR-CFG-002**: Extension MUST load `services.yaml`, inject per-listener config arrays and `%kernel.environment%`.
- **FR-DI-001**: Listeners and `SentryErrorReporter` MUST be tagged with configurable priorities; hub and logger autowired when available.

### Listeners

- **FR-LIST-001**: `SentryRequestListener` MUST run on main request only; respect `enabled` and per-flag toggles; swallow Sentry/Redis/session errors silently.
- **FR-LIST-002**: `IgnoreAccessDeniedSentryListener` MUST invoke on `AccessDeniedException` with high priority (default 255) and prevent Sentry capture when enabled.
- **FR-LIST-003**: `SentryUptimeBotListener` MUST match configurable user-agent prefixes and paths; return early OK for probes when enabled.

### Service

- **FR-SVC-001**: `SentryErrorReporter` MUST expose safe `captureException`, `captureMessage`, breadcrumb helpers; never throw; optional PSR-3 fallback logging on failure.

### Config file helper

- **FR-CFG-003**: `Configuration::generateConfigFile()` MUST write documented default YAML when `symfony/yaml` is available (used by install flows/tests).

---

## Edge Cases

- Sentry hub null or package absent: all listeners/reporter no-op without exception.
- Session unavailable (Redis failure): request listener continues without session extra.
- Sub-requests: request listener skips non-main requests.

---

## Success Criteria

- **SC-001**: **8/8** production files mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: 100% PHPUnit line coverage on `src/` (project standard).
- **SC-003**: Per-listener disable flags verified in integration tests.

---

## Validation

`composer qa`, `make test-coverage-100` when configured.

---

## Out of scope

- Sentry DSN configuration (owned by `sentry/sentry-symfony`).
- Performance monitoring sampling rules beyond scope enrichment.

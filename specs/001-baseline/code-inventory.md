# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/sentry-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. PHPUnit under `tests/` is out of scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoSentryBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Listener toggles + YAML generator | FR-CFG-001 |
| `DependencyInjection/NowoSentryExtension.php` | DI extension | FR-CFG-002 |
| `EventListener/SentryRequestListener.php` | Request/user scope enrichment | FR-LIST-001 |
| `EventListener/IgnoreAccessDeniedSentryListener.php` | Filter 403 noise | FR-LIST-002 |
| `EventListener/SentryUptimeBotListener.php` | Uptime probe handling | FR-LIST-003 |
| `Service/SentryErrorReporter.php` | Programmatic capture API | FR-SVC-001 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Listener + service wiring | FR-DI-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 7 | 7 |
| YAML config | 1 | 1 |
| **Total `src/` artifacts** | **8** | **8** |

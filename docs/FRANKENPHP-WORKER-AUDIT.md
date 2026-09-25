# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/sentry-bundle` (`symfony-bundle`) |
| Audited revision | `v1.10.0` (remediation on top of `v1.9.8` / `c2e0178`) |
| Audit date | 2026-09-23 (docs finalized 2026-09-25) |
| Method | Manual review of every file under `src/` (listeners, services, DBAL middleware, DI extension, compiler passes, `Resources/config/*.yaml`); cross-check of the Sentry scope lifecycle in the installed `sentry/sentry-symfony` 5.13.0 (dev dependency) |
| **Verdict** | ✅ **Viable under scenario B** (after remediation) — `sentry/sentry-symfony` ≥ 5.10 (now required) isolates the Sentry scope per main request through kernel events, the SQL de-duplication registry uses a `WeakMap` and no longer needs `kernel.reset`, and per-call capture context is scoped to its event |
| Remediation (2026-09-23) | W-01: constraint raised to `^5.10 \|\| ^6.0` (`composer.json`, lock hash, README/INSTALLATION). W-02: `ReportedSqlExceptionRegistry` uses `WeakMap<Throwable, true>`. W-03: `SentryErrorReporter::captureException()` / `captureMessage()` use `withScope()`. Regression tests: `tests/Unit/Doctrine/DBAL/ReportedSqlExceptionRegistryTest.php`, `tests/Unit/Service/SentryErrorReporterWorkerModeTest.php` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `ReportedSqlExceptionRegistry` holds a `WeakMap` whose entries die with the exception; every other service only has `readonly` config |
| Static properties / `static` locals | ✅ | None; `AccessDeniedExceptionHelper` and `SqlExceptionHelper` only have pure static methods |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `ReportedSqlExceptionRegistry` keeps its `kernel.reset` tag (A) but is correct without it (B) thanks to the `WeakMap` |
| Request / user / locale captured in services | ✅ | Nothing is stored in bundle properties; user id, session id and tags go to the Sentry scope, isolated per main request by `sentry/sentry-symfony` ≥ 5.10 (now required); SQL extras of `captureException()` live in a temporary scope |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used at runtime |
| Doctrine / EntityManager | ✅ | DBAL middleware only wraps calls and rethrows; no entities, no EntityManager access |
| Output, headers, `exit`, shutdown functions | ✅ | None; the uptime listener returns a `Response` object |
| Resources (files, sockets, cURL) held open | ✅ | None in the bundle; transport is owned by the Sentry SDK |
| Memory growth across requests | ✅ | Registry entries are weak references |
| Blocking I/O and timeouts | ✅ | No I/O in the bundle itself (Sentry transport is outside its scope) |
| Third-party static state | ✅ | Sentry SDK keeps a global hub (`SentrySdk` / `HubAdapter::getInstance()`); per-request isolation is provided by `sentry/sentry-symfony` ≥ 5.10 (`RuntimeContextListener`), which the bundle now requires |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist:16-17` |

A worker demo exists: `demo/symfony8/docker/frankenphp/Caddyfile:15` declares a `worker` block (`Caddyfile.dev` runs in classic mode).

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\SentryBundle\EventListener\SentryRequestListener` | yes | none in the service (`readonly` config); writes tags, user and session id into the Sentry scope | ✅ (W-01 resolved) | ✅ (W-01 resolved) |
| `Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener` | yes | none; writes `access_denied.*` tags/extras into the Sentry scope | ✅ | ✅ |
| `Nowo\SentryBundle\EventListener\SentryUptimeBotListener` | yes | none (`readonly` config) | ✅ | ✅ |
| `nowo_sentry.before_send_handler` (`BeforeSendHandler`) | yes | none; reads the registry | ✅ | ✅ (W-02 resolved) |
| `nowo_sentry.before_send_transaction_handler` (`BeforeSendTransactionHandler`) + `EventPayloadTrimmer` | yes | none (`readonly` config; trimmer built once in constructor) | ✅ | ✅ |
| `nowo_sentry.before_send_chain` / `nowo_sentry.before_send_transaction_chain` (`BeforeSendChain`, created by compiler passes) | yes | none (`readonly` callables) | ✅ | ✅ |
| `Nowo\SentryBundle\Service\SentryErrorReporter` (public, alias `nowo_sentry.error_reporter`) | yes | none in the service; `captureException()` / `captureMessage()` / `setUser()` / `setContext()` write into the Sentry scope | ✅ (W-01, W-03 resolved) | ✅ (W-01, W-03 resolved) |
| `Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry` | yes, `kernel.reset` | `WeakMap<Throwable, true> $reported` | ✅ (reset) | ✅ (weak entries, W-02 resolved) |
| `Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter` | yes | none (`readonly`) | ✅ | ✅ |
| `Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionMiddleware` | yes (one per connection, via `doctrine.middleware`) | `$connectionName`, set once by DoctrineBundle at container build (`setConnectionName()`), never changed per request | ✅ | ✅ |

`SentryDbalExceptionDriver`, `SentryDbalExceptionConnection` and `SentryDbalExceptionStatement` are `@internal` decorators created by DBAL when a connection or statement is opened; they only hold `readonly` references and the SQL string of their own statement. `Configuration::generateConfigFile()` (`mkdir` / `file_put_contents`) is `@internal` tooling and is not called on the request path.

## Findings

### W-01 — Scope data (user, session id, SQL, tags) is only isolated per request by `sentry/sentry-symfony` ≥ 5.10 (Medium)

- **Where:** `src/EventListener/SentryRequestListener.php:91-117` (`setTag('domain')`, `setTag('environment')`, `setUser()` at lines 102-107 only when an identifier exists, `setExtra('session_id')` at 109-111); `src/EventListener/SubRequestAccessDeniedContextListener.php:64-75`; `src/Service/SentryErrorReporter.php:70-78`, `122-126`, `193-195`, `229-231`, `262-266`; `src/Doctrine/DBAL/SqlExceptionReporter.php:49-54`. `composer.json` requires `sentry/sentry-symfony: ^5.0 || ^6.0`.
- **Worker impact:** all these calls use `HubInterface::configureScope()`, which mutates the current scope of the global Sentry hub (the injected `HubInterface` is `HubAdapter::getInstance()`, a process-wide singleton). The bundle never pushes/pops a scope of its own. In the installed `sentry/sentry-symfony` 5.13.0, `RuntimeContextListener` calls `SentrySdk::startContext()` on `kernel.request` (priority 512) and `SentrySdk::endContext()` on `kernel.terminate` and in `reset()` (`kernel.reset`), so each main request gets a fresh scope; this works under A and B because it is driven by kernel events. The changelog places this feature in 5.10.0. With an older 5.x release allowed by the constraint, nothing clears the scope: when user X is followed by an anonymous request, `SentryRequestListener` does not call `setUser()` and events of the second request are attributed to X; the `sql`, `connection`, `session_id` and `access_denied.*` extras of one request are attached to later events of other users. The data is not shown to end users, but Sentry issues get wrong user attribution and may carry another user's SQL literals or session id.
- **Recommendation:** raise the requirement to `sentry/sentry-symfony: ^5.10 || ^6.0` (or document it as mandatory for worker mode). Keep the bundle's `request_listener.priority` below 512 so it runs after the runtime context starts; if it ran first, the data would land in the global baseline that is copied into every new context.
- **Status:** Resolved — `composer.json` now requires `sentry/sentry-symfony: ^5.10 || ^6.0` (lock hash refreshed; README and `docs/INSTALLATION.md` updated). Confirmed in the installed 5.13.0: `RuntimeContextListener` calls `SentrySdk::startContext()` on main `kernel.request` and `endContext()` on main `kernel.terminate` / `reset()`. The bundle's `request_listener.priority` default stays `0` (< 512). `setUser()` / `setContext()` on `nowo_sentry.error_reporter` intentionally keep writing to the request scope (documented behaviour).

### W-02 — `ReportedSqlExceptionRegistry` keys on `spl_object_id()` without holding the object (Medium, scenario B)

- **Where:** `src/Doctrine/DBAL/ReportedSqlExceptionRegistry.php:15` (`array $reported`), `:19` (`markReported()`), `:27` (`isReported()`), `:37-40` (`reset()`); tagged `kernel.reset` in `src/Resources/config/doctrine_dbal.yaml:3-5`. Read by `src/Sentry/BeforeSendHandler.php:59-72`.
- **Worker impact:** `spl_object_id()` values are reused by PHP as soon as the original exception is freed. The registry keeps only the integer, not the exception. Under A, `reset()` empties it after each request, so the risk is limited to id reuse inside one request. Under B the array is never emptied: it grows with every reported SQL error for the life of the worker, and a new, unrelated exception that gets a recycled id is treated as "already reported" — `SqlExceptionReporter::report()` skips it (line 42) and `BeforeSendHandler` drops the event (line 52). Result: silently lost Sentry errors.
- **Recommendation:** store `WeakMap<Throwable, true>` instead of an id array. Entries disappear with the exception, ids can never collide, and the service stays correct even without `kernel.reset`. Keep the `kernel.reset` tag.
- **Status:** Resolved — `src/Doctrine/DBAL/ReportedSqlExceptionRegistry.php` stores `WeakMap<Throwable, true>`; `reset()` replaces the map; the `kernel.reset` tag is kept. Test: `ReportedSqlExceptionRegistryTest::testConsecutiveRequestsWithoutResetDoNotLeakOrCollide` (exception of request 1 freed, map empty, new exception of request 2 not treated as reported).

### W-03 — Per-call context of `captureException()` / `captureMessage()` stays on the scope (Low)

- **Where:** `src/Service/SentryErrorReporter.php:69-79` and `121-127`; used by `src/Doctrine/DBAL/SqlExceptionReporter.php:49-54`.
- **Worker impact:** the `$context` / `$message` meant for one event are written with `configureScope()` + `setExtra()`, so they are also attached to every later event of the same request (for example the `sql` extra of a caught DBAL error appears on an unrelated exception later in the page). With W-01 satisfied this is bounded to one request; it is a hygiene issue, not a cross-request leak.
- **Recommendation:** use `$hub->withScope(static function (Scope $scope) use (...) { ...; return $hub->captureException($e); })` so per-call data is discarded after the capture.
- **Status:** Resolved — `src/Service/SentryErrorReporter.php` applies `$context` / `$message` inside `withScope()` and captures within it; calls without context still go straight to the hub. Test: `SentryErrorReporterWorkerModeTest` (real `Sentry\State\Hub`: the `sql` / `connection` / `custom_message` extras of one capture are absent from later events).

No other findings. Listeners keep no per-request data in their own properties, there are no static properties, no superglobals and no native output.

## Usage recommendations in worker mode

- `sentry/sentry-symfony` 5.10 or newer is required (5.13.0 is the version tested in `require-dev`). Do not disable its `RuntimeContextListener`.
- Keep `nowo_sentry.request_listener.priority` below 512 (default `0`).
- Calls to `nowo_sentry.error_reporter` `setUser()` / `setContext()` from application code are safe only because of the per-request runtime context; do not call them from long-lived code outside a request (e.g. at kernel boot), because that data would become the baseline of every future request.
- The Sentry SDK flushes buffered events in `endContext()` on `kernel.terminate`; configure the Sentry transport `http_timeout` / `http_connect_timeout` so a slow Sentry endpoint does not hold worker threads.

## Re-audit triggers

Re-run this audit when a change adds: a new property or cache to any listener or to `SentryErrorReporter`, a new registry or collector, direct use of `SentrySdk` / `HubAdapter`, a change to the `sentry/sentry-symfony` version constraint, or a change to listener priorities.

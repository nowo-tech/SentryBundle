# Configuration Reference

This document provides a complete reference for all configuration options available in the Sentry Bundle.


## Table of contents

- [Viewing Configuration](#viewing-configuration)
- [Configuration File](#configuration-file)
- [Complete Configuration Reference](#complete-configuration-reference)
  - [Root Configuration](#root-configuration)
  - [Request Listener Configuration](#request-listener-configuration)
  - [Ignore Access Denied Listener Configuration](#ignore-access-denied-listener-configuration)
  - [Uptime Bot Listener Configuration](#uptime-bot-listener-configuration)
  - [Before Send Transaction Handler Configuration](#before-send-transaction-handler-configuration)
  - [DBAL Exception Reporter Configuration](#dbal-exception-reporter-configuration)
- [Complete Configuration Example](#complete-configuration-example)
- [Default Configuration](#default-configuration)
- [Environment-Specific Configuration](#environment-specific-configuration)
- [Useful Commands](#useful-commands)
  - [Dump Configuration](#dump-configuration)
  - [Validate Configuration](#validate-configuration)
  - [Debug Container](#debug-container)
  - [Error Reporter Service Configuration](#error-reporter-service-configuration)
- [Notes](#notes)
- [Troubleshooting](#troubleshooting)
  - [Listener Not Working](#listener-not-working)
  - [Configuration Not Applied](#configuration-not-applied)

## Viewing Configuration

To view the current bundle configuration, you can use the following command:

```bash
php bin/console config:dump nowo_sentry
```

This command will display the current bundle configuration in the console.

## Configuration File

The bundle configuration is defined in `config/packages/nowo_sentry.yaml`. 

**Installation**: The Symfony Flex recipe creates this file with defaults. Without Flex, copy the recipe config or rely on built-in defaults (no config file required).

If you prefer to use default values without a configuration file, you can omit it and the bundle will work with defaults.

## Complete Configuration Reference

### Root Configuration

```yaml
nowo_sentry:
    # Configuration for all event listeners
```

### Request Listener Configuration

The `request_listener` section configures the `SentryRequestListener`, which enriches Sentry reports with request context information.

```yaml
nowo_sentry:
    request_listener:
        enabled: true                    # Enable/disable the request listener
        set_domain_tag: true            # Set domain tag in Sentry scope
        set_environment_tag: true       # Set environment tag in Sentry scope
        set_user_info: true             # Set user information in Sentry scope
        set_session_id: true            # Set session ID in Sentry scope extra data
        priority: 0                      # Event listener priority
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - Enables or disables the request listener completely.
  - When disabled, the listener service is removed from the container.
  - Default: `true`

- **`set_domain_tag`** (boolean, default: `true`)
  - Whether to set the domain tag in Sentry scope.
  - The domain is extracted from the request host.
  - Example: `example.com`
  - Default: `true`

- **`set_environment_tag`** (boolean, default: `true`)
  - Whether to set the environment tag in Sentry scope.
  - The environment value comes from `%kernel.environment%`.
  - Example: `prod`, `dev`, `staging`
  - Default: `true`

- **`set_user_info`** (boolean, default: `true`)
  - Whether to set user information in Sentry scope.
  - Requires Symfony Security Bundle to be installed.
  - Sets user ID and username if a user is authenticated.
  - Default: `true`

- **`set_session_id`** (boolean, default: `false`)
  - Whether to set session ID in Sentry scope extra data.
  - Only sets the session ID if a session exists and is started.
  - Disabled by default to reduce PII in error reports; enable only when session correlation is required and your privacy policy allows it.
  - Default: `false`

- **`priority`** (integer, default: `0`)
  - Event listener priority for the `kernel.request` event.
  - Higher values execute earlier.
  - Default: `0`

#### Example: Disable Request Listener

```yaml
nowo_sentry:
    request_listener:
        enabled: false
```

#### Example: Customize Request Listener

```yaml
nowo_sentry:
    request_listener:
        enabled: true
        set_domain_tag: true
        set_environment_tag: false      # Don't set environment tag
        set_user_info: true
        set_session_id: false           # Don't set session ID
        priority: 10                    # Higher priority
```

### Ignore Access Denied Configuration

Pure access denied responses are filtered by `before_send_handler.ignore_pure_access_denied` (default `true`).

| Scenario | Sentry |
|----------|--------|
| Main request 403 (user hits URL without permission) | Ignored |
| Sub-request 403 handled in isolation | Ignored |
| Sub-request 403 breaks parent page (`RuntimeError` wrapping `AccessDeniedException`) | **Reported** |

The legacy `ignore_access_denied_listener.enabled` toggle still works and maps to `ignore_pure_access_denied`.

```yaml
nowo_sentry:
    ignore_access_denied_listener:
        enabled: true

    before_send_handler:
        enabled: true
        ignore_pure_access_denied: true
        register_automatically: true
```

#### Example: report all access denied (including pure 403)

```yaml
nowo_sentry:
    ignore_access_denied_listener:
        enabled: false
```

Or:

```yaml
nowo_sentry:
    before_send_handler:
        ignore_pure_access_denied: false
```

### Sub-request Access Denied Context Listener

Enriches Sentry when a sub-request 403 **breaks the parent page** (outer exception is not access denied, but the chain contains one).

```yaml
nowo_sentry:
    sub_request_access_denied_listener:
        enabled: true
        priority: 256
```

Adds tags such as `access_denied.origin=sub_request_broke_parent`, `access_denied.route`, `access_denied.parent_uri`.

### Before Send Handler Configuration

```yaml
nowo_sentry:
    before_send_handler:
        enabled: true
        ignore_pure_access_denied: true
        register_automatically: true
```

- **`ignore_pure_access_denied`**: drop pure access denied events (main or sub).
- **`register_automatically`**: register as `sentry.options.before_send`. If the app already defines `before_send`, the bundle chains both (bundle filter first, then the app callback). Set `false` to opt out.

When `dbal_exception_reporter` is enabled, `before_send_handler` also drops duplicate events for SQL exceptions already reported by the DBAL middleware (`deduplicate_sql_exceptions`, driven by `dbal_exception_reporter.deduplicate`).

### Before Send Transaction Handler Configuration

Trims oversized **performance transactions** before they are sent, so Relay does not reject them with:

`envelope exceeded size limits for type 'event'` (~1 MiB per event/transaction item).

Typical causes on Symfony backoffice pages: many Twig modal sub-requests, DBAL/Twig/cache spans, large request bodies, and long breadcrumb trails.

```yaml
nowo_sentry:
    before_send_transaction_handler:
        enabled: true
        register_automatically: true
        max_spans: 400
        max_breadcrumbs: 50
        max_string_length: 2048
        max_array_keys: 50
        max_array_depth: 3
```

- **`register_automatically`**: register as `sentry.options.before_send_transaction`. If the app already defines `before_send_transaction`, the bundle chains both (bundle trimmer first, then the app callback). Set `false` to opt out.
- **`max_spans`**: keep at most N spans (0 = do not truncate spans). Extra metadata is stored in `extra.spans_truncated`.
- **`max_breadcrumbs`**: keep the newest N breadcrumbs (0 = do not truncate).
- **`max_string_length` / `max_array_keys` / `max_array_depth`**: truncate request/extra/context payloads.

Disable:

```yaml
nowo_sentry:
    before_send_transaction_handler:
        enabled: false
```

### DBAL Exception Reporter Configuration

Reports Doctrine DBAL **driver/SQL exceptions** to Sentry at query time, including errors later caught in application `catch` blocks.

**Requires** `doctrine/dbal` and `doctrine/doctrine-bundle` (optional dependencies; middleware is not registered without them).

```yaml
nowo_sentry:
    dbal_exception_reporter:
        enabled: true
        connections: []          # empty = all connections; or ['default', 'analytics']
        sql_states: []             # empty = all; e.g. ['42S22'] for column-not-found only
        priority: 20               # doctrine.middleware priority
        max_sql_length: 2000       # truncate SQL in Sentry extra data
        deduplicate: true          # suppress duplicate SDK events for the same SQL error
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - Registers `SentryDbalExceptionMiddleware` when Doctrine DBAL middleware is available.
  - Independent from `error_reporter.enabled` (each toggle controls its own feature).

- **`connections`** (array of strings, default: `[]`)
  - Doctrine connection names to monitor. Empty registers the middleware for **all** connections.

- **`sql_states`** (array of strings, default: `[]`)
  - SQLSTATE codes to report. Empty reports all driver/DBAL SQL exceptions.
  - Example: `['42S22']` limits reporting to unknown column errors.

- **`priority`** (integer, default: `20`)
  - Priority for the `doctrine.middleware` tag.

- **`max_sql_length`** (integer, default: `2000`)
  - Maximum characters of SQL stored in Sentry `extra` data.

- **`deduplicate`** (boolean, default: `true`)
  - When true, `BeforeSendHandler` drops SDK events if the exception chain was already reported by this middleware.
  - The registry is updated only after a successful capture, so the middleware’s own event is not dropped as a false duplicate.

#### Example: column-not-found only

```yaml
nowo_sentry:
    dbal_exception_reporter:
        enabled: true
        sql_states:
            - '42S22'
```

#### Example: disable SQL reporting

```yaml
nowo_sentry:
    dbal_exception_reporter:
        enabled: false
```

#### Sentry extra data

Each reported SQL error includes:

- `sql` — executed statement (truncated)
- `connection` — Doctrine connection name
- `sql_state` — SQLSTATE when available
- `reporting_source` — `nowo_sentry.dbal_exception_reporter`

### Uptime Bot Listener Configuration

The `uptime_bot_listener` section configures the `SentryUptimeBotListener`, which handles requests from uptime monitoring bots.

```yaml
nowo_sentry:
    uptime_bot_listener:
        enabled: true                    # Enable/disable the uptime bot handler
        user_agents:                     # List of user agent prefixes
            - 'SentryUptimeBot/1.0'
        paths:                           # List of paths to monitor
            - '/health'
        priority: 255                    # Event listener priority
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - Enables or disables the uptime bot listener.
  - When disabled, monitoring bot requests will be processed normally.
  - Default: `true`

- **`user_agents`** (array of strings, default: `['SentryUptimeBot/1.0']`)
  - List of user agent prefixes to detect as uptime bots.
  - The listener checks if the request User-Agent starts with any of these prefixes.
  - Add `Uptime-Kuma`, `kube-probe`, etc. when needed.
  - Default: `['SentryUptimeBot/1.0']`

- **`paths`** (array of strings, default: `['/health']`)
  - List of paths that should return OK for uptime bots.
  - Exact path match: `/health`
  - Path prefix matches apply when the path is not `/`
  - Default: `['/health']`

- **`priority`** (integer, default: `255`)
  - Event listener priority for the `kernel.request` event.
  - Higher values execute earlier.
  - Default: `255` (executes early in the request lifecycle)

#### Example: Customize Uptime Bot Detection

```yaml
nowo_sentry:
    uptime_bot_listener:
        enabled: true
        user_agents:
            - 'MyCustomBot/1.0'
            - 'HealthCheckBot'
            - 'MonitoringService'
        paths:
            - '/health'
            - '/status'
            - '/ping'
        priority: 255
```

#### Example: Disable Uptime Bot Listener

```yaml
nowo_sentry:
    uptime_bot_listener:
        enabled: false
```

## Complete Configuration Example

Here's a complete configuration example with all options:

```yaml
nowo_sentry:
    request_listener:
        enabled: true
        set_domain_tag: true
        set_environment_tag: true
        set_user_info: true
        set_session_id: false
        priority: 0
    
    ignore_access_denied_listener:
        enabled: true

    before_send_handler:
        enabled: true
        ignore_pure_access_denied: true
        register_automatically: true

    before_send_transaction_handler:
        enabled: true
        register_automatically: true
        max_spans: 400
        max_breadcrumbs: 50
        max_string_length: 2048
        max_array_keys: 50
        max_array_depth: 3

    sub_request_access_denied_listener:
        enabled: true
        priority: 256
    
    dbal_exception_reporter:
        enabled: true
        connections: []
        sql_states: []
        priority: 20
        max_sql_length: 2000
        deduplicate: true
    
    uptime_bot_listener:
        enabled: true
        user_agents:
            - 'SentryUptimeBot/1.0'
        paths:
            - '/health'
        priority: 255
```

## Default Configuration

If you don't provide any configuration, the bundle uses these defaults:

```yaml
nowo_sentry:
    request_listener:
        enabled: true
        set_domain_tag: true
        set_environment_tag: true
        set_user_info: true
        set_session_id: false
        priority: 0
    
    ignore_access_denied_listener:
        enabled: true

    before_send_handler:
        enabled: true
        ignore_pure_access_denied: true
        register_automatically: true

    before_send_transaction_handler:
        enabled: true
        register_automatically: true
        max_spans: 400
        max_breadcrumbs: 50
        max_string_length: 2048
        max_array_keys: 50
        max_array_depth: 3

    sub_request_access_denied_listener:
        enabled: true
        priority: 256
    
    dbal_exception_reporter:
        enabled: true
        connections: []
        sql_states: []
        priority: 20
        max_sql_length: 2000
        deduplicate: true
    
    uptime_bot_listener:
        enabled: true
        user_agents:
            - 'SentryUptimeBot/1.0'
        paths:
            - '/health'
        priority: 255
```

## Environment-Specific Configuration

You can configure the bundle differently for different environments:

```yaml
# config/packages/nowo_sentry.yaml
nowo_sentry:
    request_listener:
        enabled: true
        set_user_info: true
        set_session_id: true

# config/packages/dev/nowo_sentry.yaml
nowo_sentry:
    request_listener:
        set_session_id: false  # Don't track sessions in dev

# config/packages/prod/nowo_sentry.yaml
nowo_sentry:
    request_listener:
        enabled: true
        set_user_info: true
        set_session_id: true
    uptime_bot_listener:
        enabled: true
        paths:
            - '/health'
            - '/status'
```

## Useful Commands

### Dump Configuration

Shows the current bundle configuration:

```bash
php bin/console config:dump nowo_sentry
```

### Validate Configuration

Validates that your configuration is correct:

```bash
php bin/console config:validate
```

### Debug Container

Check if listeners are registered:

```bash
php bin/console debug:event-dispatcher kernel.request
php bin/console debug:event-dispatcher kernel.exception
```

### Error Reporter Service Configuration

The `error_reporter` section configures the `SentryErrorReporter` service, which provides a safe way to report errors to Sentry without breaking your application.

```yaml
nowo_sentry:
    error_reporter:
        enabled: true                    # Enable/disable the error reporter service
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - When `true`, registers the public `SentryErrorReporter` service and alias `nowo_sentry.error_reporter`.
  - When `false`, removes the public alias. The class definition is removed unless `dbal_exception_reporter` still needs it as a private helper.
  - Does **not** disable DBAL SQL reporting — use `dbal_exception_reporter.enabled` for that.
  - Default: `true`

#### Usage

The `SentryErrorReporter` service is automatically available in your controllers and services via dependency injection:

```php
use Nowo\SentryBundle\Service\SentryErrorReporter;

class MyController extends AbstractController
{
    public function myAction(SentryErrorReporter $errorReporter): Response
    {
        // Capture an exception safely
        try {
            $this->doSomething();
        } catch (\Throwable $e) {
            $errorReporter->captureException($e, ['context' => 'data']);
        }
        
        // Capture a message
        $errorReporter->captureMessage('Something happened', 'warning');
        
        // Add breadcrumbs
        $errorReporter->addBreadcrumb('User action', 'info');
        
        return new Response('OK');
    }
}
```

#### Available Methods

- **`captureException(Throwable $exception, array $context = [], ?string $message = null): bool`**
  - Captures an exception to Sentry safely.
  - Returns `true` if successful, `false` otherwise.
  - Never throws an exception, even if Sentry fails.

- **`captureMessage(string $message, string $level = 'error', array $context = []): bool`**
  - Captures a message to Sentry with a severity level.
  - Levels: `debug`, `info`, `warning`, `error`, `fatal`.
  - Returns `true` if successful, `false` otherwise.

- **`captureError(string $message, array $context = [], string $level = 'error'): bool`**
  - Convenience method for capturing errors.
  - Same as `captureMessage` with default level `error`.

- **`addBreadcrumb(string $message, string $level = 'info', array $data = []): bool`**
  - Adds a breadcrumb to track user actions.
  - Breadcrumbs are included in error reports.

- **`setUser(array $userData): bool`**
  - Sets user context for all subsequent error reports.
  - User data: `id`, `username`, `email`, etc.

- **`setContext(array $context): bool`**
  - Sets additional context data for all subsequent error reports.

#### Key Features

- ✅ **Safe**: All operations are wrapped in try-catch blocks
- ✅ **Never breaks**: Failures in Sentry never throw exceptions
- ✅ **Logging**: Automatically logs errors if Sentry fails (if logger is available)
- ✅ **Flexible**: Supports exceptions, messages, breadcrumbs, and context

## Notes

- All listeners are **enabled by default** with sensible defaults.
- When a listener is disabled, its service is **removed from the container** (not just inactive).
- Priority values determine the **execution order** of event listeners:
  - Higher values execute earlier
  - Default priorities are chosen to work well with Symfony's default listeners
- The bundle **complements** the official Sentry Symfony bundle, so your existing `config/packages/sentry.yaml` configuration continues to work.
- The `request_listener` requires Symfony Security Bundle for user information features.
- The `uptime_bot_listener` returns a simple `200 OK` response for matching requests, preventing them from going through the full application stack.
- The `error_reporter` service is **always safe to use** - it never throws exceptions, even if Sentry is completely broken.
- **`dbal_exception_reporter`** is a no-op when Doctrine DBAL/Bundle is not installed; disable with `enabled: false` if you do not want SQL reporting.
- SQL deduplication uses `ReportedSqlExceptionRegistry` with **`kernel.reset`** — safe for FrankenPHP worker mode.

### Privacy (PII) sent to Sentry

Review your privacy policy before enabling optional fields. By default this bundle may attach:

| Source | Data | Default |
|--------|------|---------|
| `request_listener` | Tag `domain` (request host) | on |
| `request_listener` | Tag `environment` (`%kernel.environment%`) | on |
| `request_listener` | User `id` / `username` (Security user identifier) | on |
| `request_listener` | Extra `session_id` | **off** (`set_session_id: false`) |
| `sub_request_access_denied_listener` | Tags `access_denied.*`; extras route/URI/controller (and parent request when available) | on |
| `dbal_exception_reporter` | Extra `sql` (truncated), `connection`, `sql_state` | on when Doctrine is present |

SQL statements and user identifiers can contain personal or sensitive data. Prefer `set_session_id: false`, restrict `sql_states`, and/or lower `max_sql_length` when needed. Sentry project scrubbing rules remain the last line of defense.

## Troubleshooting

### Listener Not Working

1. Check if the listener is enabled:
   ```bash
   php bin/console config:dump nowo_sentry
   ```

2. Verify the listener is registered:
   ```bash
   php bin/console debug:event-dispatcher kernel.request
   ```

3. Check Symfony logs for errors:
   ```bash
   tail -f var/log/dev.log
   ```

### Configuration Not Applied

1. Clear the cache:
   ```bash
   php bin/console cache:clear
   ```

2. Validate your configuration:
   ```bash
   php bin/console config:validate
   ```

3. Check for syntax errors in your YAML file.


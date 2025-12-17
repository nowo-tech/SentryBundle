# Configuration Reference

This document provides a complete reference for all configuration options available in the Sentry Bundle.

## Viewing Configuration

To view the current bundle configuration, you can use the following command:

```bash
php bin/console config:dump nowo_sentry
```

This command will display the current bundle configuration in the console.

## Configuration File

The bundle configuration is defined in `config/packages/nowo_sentry.yaml`. 

**Automatic Generation**: This file is automatically created when the bundle is installed if it doesn't already exist. The bundle will generate it with default values during the first boot.

If you prefer to use default values without a configuration file, you can delete it and the bundle will work with defaults.

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

- **`set_session_id`** (boolean, default: `true`)
  - Whether to set session ID in Sentry scope extra data.
  - Only sets the session ID if a session exists and is started.
  - Default: `true`

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

### Ignore Access Denied Listener Configuration

The `ignore_access_denied_listener` section configures the `IgnoreAccessDeniedSentryListener`, which prevents `AccessDeniedException` from being reported to Sentry.

```yaml
nowo_sentry:
    ignore_access_denied_listener:
        enabled: true                    # Enable/disable the access denied filter
        priority: 255                   # Event listener priority
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - Enables or disables the ignore access denied listener.
  - When disabled, `AccessDeniedException` will be reported to Sentry normally.
  - Default: `true`

- **`priority`** (integer, default: `255`)
  - Event listener priority for the `kernel.exception` event.
  - Higher values execute earlier.
  - Default: `255` (executes before security check listener)

#### Example: Disable Access Denied Filter

```yaml
nowo_sentry:
    ignore_access_denied_listener:
        enabled: false
```

#### Example: Change Priority

```yaml
nowo_sentry:
    ignore_access_denied_listener:
        enabled: true
        priority: 200                   # Lower priority
```

### Uptime Bot Listener Configuration

The `uptime_bot_listener` section configures the `SentryUptimeBotListener`, which handles requests from uptime monitoring bots.

```yaml
nowo_sentry:
    uptime_bot_listener:
        enabled: true                    # Enable/disable the uptime bot handler
        user_agents:                     # List of user agent prefixes
            - 'SentryUptimeBot/1.0'
            - 'Uptime-Kuma'
            - 'kube-probe'
        paths:                           # List of paths to monitor
            - '/dashboard'
            - '/'
            - '/login'
        priority: 255                    # Event listener priority
```

#### Options

- **`enabled`** (boolean, default: `true`)
  - Enables or disables the uptime bot listener.
  - When disabled, monitoring bot requests will be processed normally.
  - Default: `true`

- **`user_agents`** (array of strings, default: `['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe']`)
  - List of user agent prefixes to detect as uptime bots.
  - The listener checks if the request User-Agent starts with any of these prefixes.
  - Default: `['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe']`

- **`paths`** (array of strings, default: `['/dashboard', '/', '/login']`)
  - List of paths that should return OK for uptime bots.
  - Exact path matches: `/` or `/login`
  - Path prefix matches: `/dashboard` matches `/dashboard` and `/dashboard/anything`
  - Default: `['/dashboard', '/', '/login']`

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
        set_session_id: true
        priority: 0
    
    ignore_access_denied_listener:
        enabled: true
        priority: 255
    
    uptime_bot_listener:
        enabled: true
        user_agents:
            - 'SentryUptimeBot/1.0'
            - 'Uptime-Kuma'
            - 'kube-probe'
        paths:
            - '/dashboard'
            - '/'
            - '/login'
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
        set_session_id: true
        priority: 0
    
    ignore_access_denied_listener:
        enabled: true
        priority: 255
    
    uptime_bot_listener:
        enabled: true
        user_agents:
            - 'SentryUptimeBot/1.0'
            - 'Uptime-Kuma'
            - 'kube-probe'
        paths:
            - '/dashboard'
            - '/'
            - '/login'
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
  - Enables or disables the error reporter service.
  - When disabled, the service is still available but may not function as expected.
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
- The bundle **extends** the official Sentry Symfony bundle, so your existing `config/packages/sentry.yaml` configuration continues to work.
- The `request_listener` requires Symfony Security Bundle for user information features.
- The `uptime_bot_listener` returns a simple `200 OK` response for matching requests, preventing them from going through the full application stack.
- The `error_reporter` service is **always safe to use** - it never throws exceptions, even if Sentry is completely broken.

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


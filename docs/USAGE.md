# Usage

The bundle works out of the box with your existing Sentry configuration. No additional setup is required for basic error reporting.

## Event listeners

The bundle registers these components by default (each can be enabled/disabled in [configuration](CONFIGURATION.md)):

### SentryRequestListener

Enriches Sentry reports with request context:

- Sets domain and environment tags
- Configures user information when available
- Adds session ID to extra data (when enabled)

### BeforeSendHandler

Filters events before they are sent to Sentry:

- Drops **pure** `AccessDeniedException` / `AccessDeniedHttpException` (main or sub-request)
- Keeps parent-page failures that wrap a sub-request 403 (e.g. Twig rendering error)
- Deduplicates SQL exceptions already reported by `dbal_exception_reporter`

The legacy `ignore_access_denied_listener.enabled` toggle maps to `before_send_handler.ignore_pure_access_denied`.

### SubRequestAccessDeniedContextListener

When a sub-request access denied **breaks the parent page**, enriches Sentry with `access_denied.*` tags (route, URI, parent route).

### SentryUptimeBotListener

Handles requests from uptime monitoring bots (Sentry Uptime Bot, Uptime-Kuma, kube-probe) by returning a simple OK response for configured paths (e.g. `/dashboard`, `/`, `/login`).

### DBAL exception reporter (optional)

When `doctrine/dbal` and `doctrine/doctrine-bundle` are installed and `dbal_exception_reporter.enabled=true`:

- A Doctrine **driver middleware** intercepts failed queries
- SQL/driver exceptions are sent to Sentry with SQL, connection, and SQLSTATE context
- The exception is **rethrown** — application behaviour is unchanged
- Errors caught in your `catch` blocks still reach Sentry (unlike SDK-only capture)

Typical use case: MySQL/PostgreSQL schema drift (`SQLSTATE 42S22` column not found) swallowed by `catch (Throwable) {}` or secondary persistence failures.

```yaml
nowo_sentry:
    dbal_exception_reporter:
        enabled: true
        sql_states: []   # or ['42S22'] for column-not-found only
```

Demo routes: `/sentry/sql-caught`, `/sentry/sql-uncaught` (see [`demo/README.md`](../demo/README.md)).

## SentryErrorReporter service

A safe service for reporting errors to Sentry without breaking your application. All operations are wrapped in try-catch so that Sentry failures do not affect the application flow.

### Features

- Safe error reporting (never throws)
- Support for exceptions, messages, and context data
- Automatic logging if Sentry fails
- Configurable levels
- Breadcrumb and user context

### Basic example

```php
use Nowo\SentryBundle\Service\SentryErrorReporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class MyController extends AbstractController
{
    public function myAction(SentryErrorReporter $errorReporter): Response
    {
        try {
            $this->doSomething();
        } catch (\Throwable $e) {
            $errorReporter->captureException(
                $e,
                ['context' => 'data'],
                'Custom message'
            );
        }

        $errorReporter->captureMessage('Something happened', 'warning', ['data' => 'value']);
        $errorReporter->addBreadcrumb('User performed action', 'info', ['action' => 'click']);

        return new Response('OK');
    }
}
```

### API summary

- `captureException(\Throwable $e, array $context = [], ?string $message = null): bool`
- `captureMessage(string $message, string $level = 'error', array $context = []): bool`
- `captureError(string $message, array $context = [], string $level = 'error'): bool`
- `addBreadcrumb(string $message, string $level = 'info', array $data = []): bool`
- `setUser(array $userData): bool`
- `setContext(array $context): bool`

See the demo projects (`demo/symfony7`, `demo/symfony8`, `demo/symfony8-php85`) for full examples and routes.

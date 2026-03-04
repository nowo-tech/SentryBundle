# Usage

The bundle works out of the box with your existing Sentry configuration. No additional setup is required for basic error reporting.

## Event listeners

The bundle registers these event listeners by default (each can be enabled/disabled in [configuration](CONFIGURATION.md)):

### SentryRequestListener

Enriches Sentry reports with request context:

- Sets domain and environment tags
- Configures user information when available
- Adds session ID to extra data

### IgnoreAccessDeniedSentryListener

Prevents `AccessDeniedException` from being sent to Sentry, reducing noise in error tracking.

### SentryUptimeBotListener

Handles requests from uptime monitoring bots (Sentry Uptime Bot, Uptime-Kuma, kube-probe) by returning a simple OK response for configured paths (e.g. `/dashboard`, `/`, `/login`).

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

- `captureException(\Throwable $e, array $context = [], ?string $message = null): void`
- `captureMessage(string $message, string $level = 'error', array $context = []): void`
- `captureError(string $message, array $context = []): void`
- `addBreadcrumb(string $message, string $level = 'info', array $context = []): void`
- `setUserContext(?string $id, ?string $email = null, ?string $username = null, array $extra = []): void`
- `setContext(string $key, array $data): void`

See the demo projects (`demo/symfony7`, `demo/symfony8`) for full examples and routes.

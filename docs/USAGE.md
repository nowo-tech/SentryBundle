# Usage

The bundle works out of the box with your existing Sentry configuration. No additional setup is required for basic error reporting.

## Event listeners

The bundle registers these components by default (each can be enabled/disabled in [configuration](CONFIGURATION.md)):

### SentryRequestListener

Enriches Sentry reports with request context:

- Sets domain and environment tags
- Configures user information when available, through `SentryUserContextProviderInterface` (1.12+): by default the
  user's `getId()` or a hash of the identifier, never the e-mail unless `send_user_identifier` / `send_default_pii`
  opts in. A DDD host can send an opaque domain id:

```php
final class MemberIdSentryUserContextProvider implements SentryUserContextProviderInterface
{
    public function getUserContext(UserInterface $user): ?array
    {
        return $user instanceof SecurityMember ? ['id' => $user->memberId()->toString()] : null;
    }
}
```

```yaml
nowo_sentry:
  request_listener:
    user_context_provider: App\Infrastructure\Sentry\MemberIdSentryUserContextProvider
```
- Adds session ID to extra data (when enabled)

### BeforeSendHandler

Filters events before they are sent to Sentry:

- Drops **pure** `AccessDeniedException` / `AccessDeniedHttpException` (main or sub-request)
- Keeps parent-page failures that wrap a sub-request 403 (e.g. Twig rendering error)
- Deduplicates SQL exceptions already reported by `dbal_exception_reporter`

The legacy `ignore_access_denied_listener` toggle (deprecated since 1.7) still maps to `before_send_handler.ignore_pure_access_denied` when set explicitly, and triggers a deprecation. Prefer the latter; omit the legacy key from YAML.

### SubRequestAccessDeniedContextListener

When a sub-request access denied **breaks the parent page**, enriches Sentry with `access_denied.*` tags (route, URI, parent route).

### SentryUptimeBotListener

Handles requests from uptime monitoring bots (Sentry Uptime Bot, Uptime-Kuma, kube-probe) by returning a simple OK response for configured paths (default `/health`; a configured path also covers its sub-paths such as `/health/db`, never sibling paths such as `/healthz`).

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

Type-hint `Nowo\SentryBundle\Service\ErrorReporterInterface` (1.13) instead of the final class; the alias resolves to
the same service.

### API summary

- `captureException(\Throwable $e, array $context = [], ?string $message = null): bool`
- `captureMessage(string $message, string $level = 'error', array $context = []): bool`
- `captureError(string $message, array $context = [], string $level = 'error'): bool`
- `addBreadcrumb(string $message, string $level = 'info', array $data = []): bool`
- `setUser(array $userData): bool`
- `setContext(array $context): bool`

See the demo project (`demo/symfony8`) for full examples and routes.

## Standard Symfony and DDD / hexagonal hosts

Both paths are supported (REQ-ARCH-001); the standard one is the zero-config default.

### Standard host

Install, keep the defaults: the request listener sets the domain / environment tags and a privacy-safe user (stable
`getId()` or an HMAC of the identifier), and you inject `ErrorReporterInterface` (or `SentryErrorReporter`) where you
report by hand. A 1.12 `SentryUserContextProviderInterface` provider keeps working until 2.0 (a build-time deprecation
names it).

### DDD host

Keep the domain free of Sentry and Symfony types:

- Report through your own domain port, implemented by an adapter over `ErrorReporterInterface`:

  ```php
  final readonly class SentryIncidentReporter implements IncidentReporter      // your domain port
  {
      public function __construct(private ErrorReporterInterface $reporter) {}

      public function incident(\Throwable $failure, string $boundedContext): void
      {
          $this->reporter->captureException($failure, ['bounded_context' => $boundedContext]);
      }
  }
  ```

- Send an opaque domain id instead of the e-mail with a framework-free provider (no `UserInterface` in your code):

  ```php
  use Nowo\SentryBundle\User\UserContextProviderInterface;
  use Nowo\SentryBundle\User\UserRef;

  final readonly class MemberIdProvider implements UserContextProviderInterface
  {
      public function __construct(private MemberDirectory $members) {}

      public function contextFor(UserRef $user): ?array
      {
          $member = $this->members->idOf($user->identifier());

          return $member === null ? null : ['id' => $member];
      }
  }
  ```

  ```yaml
  # config/services.yaml (or request_listener.user_context_provider: App\Infrastructure\MemberIdProvider)
  services:
      Nowo\SentryBundle\User\UserContextProviderInterface: '@App\Infrastructure\MemberIdProvider'
  ```

  `UserRef` exposes `id` (stable `getId()` or null), `roles`, `identifier()` and `user()`; the identifier and the user
  object are not properties (internal `WeakMap`), so a dump or a serialized `UserRef` never shows them. Send only what
  your privacy policy allows: the bundle sends exactly what your provider returns. Or switch user data off with
  `request_listener.set_user_info: false`.

`tests/Integration/Ddd/DddHostPortsKernelTest` is such a host (only the new ports, 2,000 alternating requests on one
kept-alive kernel).

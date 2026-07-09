# Security policy

## Supported versions

We release security fixes for the versions currently under maintenance. Check the [releases](https://github.com/nowo-tech/SentryBundle/releases) and [CHANGELOG](CHANGELOG.md) for supported versions.

## Reporting a vulnerability

If you discover a security vulnerability in this bundle, please report it responsibly:

1. **Do not** open a public GitHub issue for security-sensitive bugs.
2. Send an email to **hectorfranco@nowo.tech** (or the maintainers listed in [composer.json](https://github.com/nowo-tech/SentryBundle/blob/main/composer.json)) with:
   - A description of the vulnerability
   - Steps to reproduce
   - Impact assessment
   - Any suggested fix (optional)

We will acknowledge receipt and work with you to understand and address the issue. We may request additional information and will keep you updated on progress and any release that includes a fix.

Thank you for helping keep Sentry Bundle and its users safe.

## Data sent to Sentry

This bundle enriches Sentry scope with request context. Review these defaults in production:

| Setting | Default | Notes |
|---------|---------|-------|
| `set_user_info` | `true` | Sends authenticated user id/username when available |
| `set_session_id` | `false` | Disabled by default to reduce PII in error reports |

Enable `set_session_id` only when session correlation is required and your privacy policy allows it.

## Scrubbing sensitive data (`before_send`)

Configure scrubbing in the host application's `config/packages/sentry.yaml` (official Sentry Symfony bundle):

```yaml
sentry:
    options:
        before_send: 'sentry.callback.before_send'
        send_default_pii: false
```

Example callback service to strip cookies and headers from events:

```php
use Sentry\Event;
use Sentry\EventHint;

final class SentryBeforeSendCallback
{
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        $request = $event->getRequest();
        if ($request !== null) {
            $request->setHeaders([]);
            $request->setCookies([]);
        }

        return $event;
    }
}
```

Also configure server-side scrubbing rules in the Sentry project settings.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **Input / output** | Inputs validated; outputs escaped in Twig/templates where user-controlled. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | If used: keys from secure config; never hardcoded. |
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Timeouts, size limits, rate limits where applicable. |

Record confirmation in the release PR or tag notes.


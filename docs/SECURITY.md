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

This bundle enriches Sentry scope with request context and optional SQL extras. Review these defaults in production (full table in [CONFIGURATION.md](CONFIGURATION.md#privacy-pii-sent-to-sentry)):

| Setting | Default | Notes |
|---------|---------|-------|
| `set_user_info` | `true` (dev); **`false` in Flex `when@prod`** | Sends authenticated user id/username when available |
| `set_session_id` | `false` | Disabled by default; prod recipe keeps it false |

### Production recommendation

Flex recipe ships:

```yaml
when@prod:
    nowo_sentry:
        request_listener:
            set_user_info: false
            set_session_id: false
```

Enable `set_user_info` / `set_session_id` in production only after an explicit privacy review. Prefer host-app `before_send` scrubbing for cookies/headers (see below) and Sentry project server-side scrubbing rules.


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

## AI security audit (REQ-SEC-004)

| Field | Value |
| ----- | ----- |
| Date | 2026-08-20 (re-audit; prior 2026-07-29) |
| Grade | **Pass (good)** — overall **Low** |
| Method | Static review; Flex `when@prod` disables `set_user_info` / keeps `set_session_id: false` |
| Open residuals | Host may re-enable user/session tags after privacy review; scrub `before_send` for cookies/headers |


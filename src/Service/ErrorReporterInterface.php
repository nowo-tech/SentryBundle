<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Service;

use Throwable;

/**
 * Port of the safe error reporter (since 1.13, REQ-ARCH-001 rule 1): type-hint this interface instead of the final
 * {@see SentryErrorReporter}. Every method reports to Sentry when it is configured and returns false instead of
 * throwing when it is not or when Sentry fails. Autowire it, or use the `nowo_sentry.error_reporter` service id.
 *
 * DDD hosts implement their own domain port (e.g. `IncidentReporter`) with an adapter over this interface.
 */
interface ErrorReporterInterface
{
    /**
     * Reports an exception (with optional extra context and message on a temporary scope).
     *
     * @param array<string, mixed> $context extra data of this event only
     *
     * @return bool true when Sentry accepted the event
     */
    public function captureException(Throwable $exception, array $context = [], ?string $message = null): bool;

    /**
     * Reports a message at a level (debug, info, warning, error, fatal).
     *
     * @param array<string, mixed> $context extra data of this event only
     *
     * @return bool true when Sentry accepted the event
     */
    public function captureMessage(string $message, string $level = 'error', array $context = []): bool;

    /**
     * Same as {@see captureMessage()} with the context before the level.
     *
     * @param array<string, mixed> $context extra data of this event only
     *
     * @return bool true when Sentry accepted the event
     */
    public function captureError(string $message, array $context = [], string $level = 'error'): bool;

    /**
     * Adds a breadcrumb to the current scope.
     *
     * @param array<string, mixed> $data breadcrumb data
     *
     * @return bool true when the breadcrumb was added
     */
    public function addBreadcrumb(string $message, string $level = 'info', array $data = []): bool;

    /**
     * Sets the user of the current scope (until it is cleared or changed). Send ids, not e-mails, unless you opted in.
     *
     * @param array<string, mixed> $userData Sentry user data (id, username, …)
     *
     * @return bool true when the user was set
     */
    public function setUser(array $userData): bool;

    /**
     * Sets extra data on the current scope.
     *
     * @param array<int|string, mixed> $context extra data (keys cast to string)
     *
     * @return bool true when the context was set
     */
    public function setContext(array $context): bool;
}

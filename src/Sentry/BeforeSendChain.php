<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Sentry;

use Sentry\Event;
use Sentry\EventHint;

use function is_callable;

/**
 * Invokes a primary before_send callable, then an optional next callable.
 *
 * Used when the application already defines sentry.options.before_send so both
 * the bundle filter and the app callback run.
 */
final class BeforeSendChain
{
    /**
     * @param callable(?Event, ?EventHint): (?Event) $primary
     * @param callable(?Event, ?EventHint): (?Event)|null $next
     */
    public function __construct(
        private readonly mixed $primary,
        private readonly mixed $next = null,
    ) {
    }

    public function __invoke(?Event $event, ?EventHint $hint): ?Event
    {
        $result = ($this->primary)($event, $hint);

        if ($result === null || !is_callable($this->next)) {
            return $result;
        }

        return ($this->next)($result, $hint);
    }
}

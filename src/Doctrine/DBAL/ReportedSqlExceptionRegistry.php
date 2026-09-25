<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL;

use Throwable;
use WeakMap;

/**
 * Tracks SQL exceptions already reported to Sentry within the current request / worker cycle.
 *
 * Entries are weak: they disappear with the exception object, so the registry cannot grow nor
 * match a recycled object id in long-running workers even when kernel.reset never runs.
 */
final class ReportedSqlExceptionRegistry
{
    /** @var WeakMap<Throwable, true> */
    private WeakMap $reported;

    public function __construct()
    {
        $this->reported = new WeakMap();
    }

    public function markReported(Throwable $exception): void
    {
        $this->reported[$exception] = true;
    }

    public function isReported(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if (isset($this->reported[$current])) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    public function reset(): void
    {
        $this->reported = new WeakMap();
    }
}

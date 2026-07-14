<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL;

use Throwable;

/**
 * Tracks SQL exceptions already reported to Sentry within the current request / worker cycle.
 */
final class ReportedSqlExceptionRegistry
{
    /** @var array<int, true> */
    private array $reported = [];

    public function markReported(Throwable $exception): void
    {
        $this->reported[spl_object_id($exception)] = true;
    }

    public function isReported(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if (isset($this->reported[spl_object_id($current)])) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    public function reset(): void
    {
        $this->reported = [];
    }
}

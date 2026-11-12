<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Fixtures\Ports;

use Nowo\SentryBundle\Service\ErrorReporterInterface;
use Throwable;

/**
 * A DDD host's own incident port adapter over {@see ErrorReporterInterface} (autowired by interface).
 */
final readonly class IncidentReporter
{
    public function __construct(
        private ErrorReporterInterface $reporter,
    ) {
    }

    public function incident(Throwable $failure, string $boundedContext): bool
    {
        return $this->reporter->captureException($failure, ['bounded_context' => $boundedContext]);
    }
}

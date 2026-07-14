<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL\Middleware;

use Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;

/**
 * DBAL driver middleware that reports SQL exceptions to Sentry before they are caught.
 */
final class SentryDbalExceptionMiddleware implements ConnectionNameAwareInterface, MiddlewareInterface
{
    private string $connectionName = 'default';

    public function __construct(
        private readonly SqlExceptionReporter $reporter,
    ) {
    }

    public function setConnectionName(string $name): void
    {
        $this->connectionName = $name;
    }

    public function wrap(Driver $driver): Driver
    {
        return new SentryDbalExceptionDriver($driver, $this->reporter, $this->connectionName);
    }
}

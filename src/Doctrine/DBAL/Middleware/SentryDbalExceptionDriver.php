<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use SensitiveParameter;

/**
 * @internal
 */
final class SentryDbalExceptionDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly SqlExceptionReporter $reporter,
        private readonly string $connectionName,
    ) {
        parent::__construct($driver);
    }

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): DriverConnection {
        return new SentryDbalExceptionConnection(
            parent::connect($params),
            $this->reporter,
            $this->connectionName,
        );
    }
}

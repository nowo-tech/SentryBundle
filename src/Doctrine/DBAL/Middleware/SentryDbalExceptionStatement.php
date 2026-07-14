<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use Throwable;

/**
 * @internal
 */
final class SentryDbalExceptionStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly SqlExceptionReporter $reporter,
        private readonly string $connectionName,
        private readonly string $sql,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        try {
            return parent::execute();
        } catch (Throwable $exception) {
            $this->reporter->report($exception, $this->sql, $this->connectionName);

            throw $exception;
        }
    }
}

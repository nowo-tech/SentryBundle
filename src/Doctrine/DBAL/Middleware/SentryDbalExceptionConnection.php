<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use Throwable;

/**
 * @internal
 */
final class SentryDbalExceptionConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly SqlExceptionReporter $reporter,
        private readonly string $connectionName,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new SentryDbalExceptionStatement(
            parent::prepare($sql),
            $this->reporter,
            $this->connectionName,
            $sql,
        );
    }

    public function query(string $sql): Result
    {
        return $this->executeWithReporting($sql, fn (): Result => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return $this->executeWithReporting($sql, fn (): int|string => parent::exec($sql));
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function executeWithReporting(string $sql, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->reporter->report($exception, $sql, $this->connectionName);

            throw $exception;
        }
    }
}

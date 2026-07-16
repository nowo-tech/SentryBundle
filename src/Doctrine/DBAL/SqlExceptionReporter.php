<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL;

use Nowo\SentryBundle\Service\SentryErrorReporter;
use Throwable;

use function is_string;
use function strlen;

/**
 * Reports SQL exceptions to Sentry, including those caught by application code.
 */
final class SqlExceptionReporter
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly SentryErrorReporter $errorReporter,
        private readonly ReportedSqlExceptionRegistry $registry,
        private readonly array $config,
    ) {
    }

    public function report(Throwable $exception, string $sql, string $connectionName): void
    {
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        if (!SqlExceptionHelper::isSqlException($exception)) {
            return;
        }

        if (!$this->matchesSqlState($exception)) {
            return;
        }

        if ($this->registry->isReported($exception)) {
            return;
        }

        $this->registry->markReported($exception);

        $this->errorReporter->captureException($exception, [
            'sql'              => $this->truncateSql($sql),
            'connection'       => $connectionName,
            'sql_state'        => SqlExceptionHelper::getSqlState($exception),
            'reporting_source' => 'nowo_sentry.dbal_exception_reporter',
        ]);
    }

    private function matchesSqlState(Throwable $exception): bool
    {
        $allowedStates = $this->config['sql_states'] ?? [];

        if ($allowedStates === []) {
            return true;
        }

        $sqlState = SqlExceptionHelper::getSqlState($exception);

        if ($sqlState === null) {
            return false;
        }

        foreach ($allowedStates as $allowedState) {
            if (!is_string($allowedState) || $allowedState === '') {
                continue;
            }

            if ($sqlState === $allowedState || str_starts_with($sqlState, $allowedState)) {
                return true;
            }
        }

        return false;
    }

    private function truncateSql(string $sql): string
    {
        $maxLength = (int) ($this->config['max_sql_length'] ?? 2000);

        if ($maxLength <= 0 || strlen($sql) <= $maxLength) {
            return $sql;
        }

        return substr($sql, 0, $maxLength) . '…';
    }
}

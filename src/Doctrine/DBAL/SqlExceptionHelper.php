<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL;

use Doctrine\DBAL\Exception\DriverException;
use Throwable;

/**
 * Detects Doctrine DBAL / driver SQL exceptions.
 */
final class SqlExceptionHelper
{
    private const DRIVER_EXCEPTION_INTERFACE = 'Doctrine\\DBAL\\Driver\\Exception';

    public static function isSqlException(Throwable $exception): bool
    {
        if ($exception instanceof DriverException) {
            return true;
        }

        return is_a($exception, self::DRIVER_EXCEPTION_INTERFACE, false);
    }

    public static function getSqlState(Throwable $exception): ?string
    {
        if ($exception instanceof DriverException) {
            return $exception->getSQLState();
        }

        if (!is_a($exception, self::DRIVER_EXCEPTION_INTERFACE, false)) {
            return null;
        }

        /* @var \Doctrine\DBAL\Driver\Exception $exception */
        return $exception->getSQLState();
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Doctrine\DBAL;

use Throwable;

/**
 * Detects Doctrine DBAL / driver SQL exceptions.
 */
final class SqlExceptionHelper
{
    public static function isSqlException(Throwable $exception): bool
    {
        if (interface_exists(\Doctrine\DBAL\Driver\Exception::class)
            && $exception instanceof \Doctrine\DBAL\Driver\Exception) {
            return true;
        }

        return class_exists(\Doctrine\DBAL\Exception\DriverException::class)
            && $exception instanceof \Doctrine\DBAL\Exception\DriverException;
    }

    public static function getSqlState(Throwable $exception): ?string
    {
        if ($exception instanceof \Doctrine\DBAL\Driver\Exception) {
            return $exception->getSQLState();
        }

        if (class_exists(\Doctrine\DBAL\Exception\DriverException::class)
            && $exception instanceof \Doctrine\DBAL\Exception\DriverException) {
            /* @var \Doctrine\DBAL\Exception\DriverException $exception */
            return $exception->getSQLState();
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SqlExceptionHelperTest extends TestCase
{
    public function testDetectsDriverExceptionInterface(): void
    {
        if (!interface_exists(Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $this->assertTrue(SqlExceptionHelper::isSqlException($exception));
        $this->assertSame('42S22', SqlExceptionHelper::getSqlState($exception));
    }

    public function testDetectsDbalDriverExceptionClassWhenAvailable(): void
    {
        if (!class_exists(DriverException::class)) {
            $this->markTestSkipped('Doctrine DBAL DriverException is not available.');
        }

        $driverException = new class extends RuntimeException implements Exception {
            public function getSQLState(): string
            {
                return '42S02';
            }
        };

        $exception = new DriverException($driverException, null);

        $this->assertTrue(SqlExceptionHelper::isSqlException($exception));
        $this->assertSame('42S02', SqlExceptionHelper::getSqlState($exception));
    }

    public function testReturnsFalseForNonSqlException(): void
    {
        $exception = new RuntimeException('Not SQL');

        $this->assertFalse(SqlExceptionHelper::isSqlException($exception));
        $this->assertNull(SqlExceptionHelper::getSqlState($exception));
    }
}

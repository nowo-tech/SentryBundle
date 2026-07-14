<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionConnection;
use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionDriver;
use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionMiddleware;
use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use Nowo\SentryBundle\Service\SentryErrorReporter;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SentryDbalExceptionMiddlewareTest extends TestCase
{
    public function testWrapsDriverAndConnectionWithConfiguredConnectionName(): void
    {
        if (!interface_exists(Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $innerConnection = $this->createMock(DriverConnection::class);
        $innerDriver     = $this->createMock(Driver::class);
        $innerDriver->expects($this->once())
            ->method('connect')
            ->with(['url' => 'sqlite:///:memory:'])
            ->willReturn($innerConnection);

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($this->createMock(HubInterface::class)),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => []],
        );
        $middleware = new SentryDbalExceptionMiddleware($reporter);
        $middleware->setConnectionName('reporting');

        $wrappedDriver = $middleware->wrap($innerDriver);

        $this->assertInstanceOf(SentryDbalExceptionDriver::class, $wrappedDriver);

        $connection = $wrappedDriver->connect(['url' => 'sqlite:///:memory:']);

        $this->assertInstanceOf(SentryDbalExceptionConnection::class, $connection);
    }
}

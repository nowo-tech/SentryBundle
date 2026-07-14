<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionConnection;
use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use Nowo\SentryBundle\Service\SentryErrorReporter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\EventId;
use Sentry\State\HubInterface;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SentryDbalExceptionConnectionTest extends TestCase
{
    public function testQueryReportsSqlExceptionAndRethrows(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $sqlException = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->expects($this->once())
            ->method('query')
            ->with('SELECT missing FROM users')
            ->willThrowException($sqlException);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $connection = new SentryDbalExceptionConnection(
            $innerConnection,
            new SqlExceptionReporter(new SentryErrorReporter($hub), new ReportedSqlExceptionRegistry(), ['enabled' => true, 'sql_states' => []]),
            'default',
        );

        $this->expectException(RuntimeException::class);
        $connection->query('SELECT missing FROM users');
    }

    public function testPrepareWrapsStatementExecution(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $sqlException = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $innerStatement = $this->createMock(Statement::class);
        $innerStatement->expects($this->once())->method('execute')->willThrowException($sqlException);

        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->expects($this->once())
            ->method('prepare')
            ->with('SELECT missing FROM users')
            ->willReturn($innerStatement);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $connection = new SentryDbalExceptionConnection(
            $innerConnection,
            new SqlExceptionReporter(new SentryErrorReporter($hub), new ReportedSqlExceptionRegistry(), ['enabled' => true, 'sql_states' => []]),
            'default',
        );

        $statement = $connection->prepare('SELECT missing FROM users');

        $this->expectException(RuntimeException::class);
        $statement->execute();
    }

    public function testExecReportsSqlExceptionAndRethrows(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $sqlException = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->expects($this->once())
            ->method('exec')
            ->with('UPDATE users SET missing = 1')
            ->willThrowException($sqlException);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $connection = new SentryDbalExceptionConnection(
            $innerConnection,
            new SqlExceptionReporter(new SentryErrorReporter($hub), new ReportedSqlExceptionRegistry(), ['enabled' => true, 'sql_states' => []]),
            'default',
        );

        $this->expectException(RuntimeException::class);
        $connection->exec('UPDATE users SET missing = 1');
    }
}

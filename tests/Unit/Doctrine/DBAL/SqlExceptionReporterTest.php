<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL;

use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionHelper;
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
final class SqlExceptionReporterTest extends TestCase
{
    public function testReportsDriverExceptionWithSqlContext(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn(EventId::generate());

        $registry = new ReportedSqlExceptionRegistry();
        $reporter = new SqlExceptionReporter(new SentryErrorReporter($hub), $registry, [
            'enabled'        => true,
            'sql_states'     => [],
            'max_sql_length' => 2000,
        ]);

        $reporter->report($exception, 'SELECT missing_column FROM users', 'default');
    }

    public function testFiltersBySqlStateWhenConfigured(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '23000';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('captureException');

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => ['42S22']],
        );

        $reporter->report($exception, 'INSERT INTO users VALUES (1)', 'default');
    }

    public function testDoesNotReportTwiceForSameException(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $registry = new ReportedSqlExceptionRegistry();
        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            $registry,
            ['enabled' => true, 'sql_states' => []],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');
        $reporter->report($exception, 'SELECT a FROM t', 'default');

        $this->assertTrue($registry->isReported($exception));
    }

    public function testDoesNotMarkRegistryWhenCaptureFails(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(null);

        $registry = new ReportedSqlExceptionRegistry();
        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            $registry,
            ['enabled' => true, 'sql_states' => []],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');

        $this->assertFalse($registry->isReported($exception));
    }

    public function testMarksRegistryOnlyAfterSuccessfulCaptureSoBeforeSendCanKeepEvent(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $registry = new ReportedSqlExceptionRegistry();
        $hub      = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturnCallback(static function () use ($registry, $exception): EventId {
                // Mimic Sentry Client: before_send runs during captureException.
                // Registry must still be empty so BeforeSendHandler does not drop this event.
                TestCase::assertFalse($registry->isReported($exception));

                return EventId::generate();
            });

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            $registry,
            ['enabled' => true, 'sql_states' => []],
        );

        $reporter->report($exception, 'SELECT missing_column FROM t', 'default');

        $this->assertTrue($registry->isReported($exception));
    }

    public function testRegistryDetectsReportedExceptionInPreviousChain(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $driverException = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };
        $wrapped = new RuntimeException('Wrapped', 0, $driverException);

        $registry = new ReportedSqlExceptionRegistry();
        $registry->markReported($driverException);

        $this->assertTrue($registry->isReported($wrapped));
        $this->assertTrue(SqlExceptionHelper::isSqlException($driverException));
    }

    public function testSkipsWhenDisabled(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('captureException');

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => false, 'sql_states' => []],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');
    }

    public function testSkipsNonSqlException(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('captureException');

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => []],
        );

        $reporter->report(new RuntimeException('Not SQL'), 'SELECT a FROM t', 'default');
    }

    public function testMatchesSqlStatePrefix(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => ['42S']],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');
    }

    public function testRejectsWhenSqlStateMissingAndFilterConfigured(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): ?string
            {
                return null;
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('captureException');

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => ['42S22']],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');
    }

    public function testIgnoresInvalidSqlStateEntries(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('captureException');

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => [123, '']],
        );

        $reporter->report($exception, 'SELECT a FROM t', 'default');
    }

    public function testTruncatesLongSql(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Exception::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $exception = new class extends RuntimeException implements \Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };

        $capturedSql = null;
        $hub         = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(static function (callable $callback) use (&$capturedSql): void {
                $scope = new class {
                    /** @var array<string, mixed> */
                    public array $extras = [];

                    public function setExtra(string $key, mixed $value): void
                    {
                        $this->extras[$key] = $value;
                    }
                };
                $callback($scope);
                $capturedSql = $scope->extras['sql'] ?? null;
            });
        $hub->expects($this->once())->method('captureException')->willReturn(EventId::generate());

        $reporter = new SqlExceptionReporter(
            new SentryErrorReporter($hub),
            new ReportedSqlExceptionRegistry(),
            ['enabled' => true, 'sql_states' => [], 'max_sql_length' => 10],
        );

        $reporter->report($exception, str_repeat('A', 20), 'default');

        $this->assertIsString($capturedSql);
        $this->assertStringEndsWith('…', $capturedSql);
        $this->assertSame('AAAAAAAAAA…', $capturedSql);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\Sentry\BeforeSendHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Test case for BeforeSendHandler.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class BeforeSendHandlerTest extends TestCase
{
    public function testDropsPureAccessDeniedException(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => true]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray(['exception' => new AccessDeniedException('Denied')]);

        $this->assertNull($handler($event, $hint));
    }

    public function testDropsPureAccessDeniedHttpException(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => true]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray(['exception' => new AccessDeniedHttpException('Denied')]);

        $this->assertNull($handler($event, $hint));
    }

    public function testKeepsParentPageFailureWrappingSubRequestAccessDenied(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => true]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray([
            'exception' => new RuntimeException(
                'An exception has been thrown during the rendering of a template.',
                0,
                new AccessDeniedException('Denied'),
            ),
        ]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testKeepsUnrelatedRuntimeException(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => true]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray(['exception' => new RuntimeException('Other error')]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testKeepsPureAccessDeniedWhenFilteringIsDisabled(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => false]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray(['exception' => new AccessDeniedException('Denied')]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testDropsDuplicateSqlExceptionAlreadyReportedByMiddleware(): void
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
        $exception = new RuntimeException('Converted', 0, $driverException);

        $registry = new ReportedSqlExceptionRegistry();
        $registry->markReported($driverException);

        $handler = new BeforeSendHandler(
            ['enabled' => true, 'ignore_pure_access_denied' => true, 'deduplicate_sql_exceptions' => true],
            $registry,
        );
        $event = Event::createEvent();
        $hint  = EventHint::fromArray(['exception' => $exception]);

        $this->assertNull($handler($event, $hint));
    }

    public function testKeepsDuplicateSqlExceptionWhenDeduplicationDisabled(): void
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
        $exception = new RuntimeException('Converted', 0, $driverException);

        $registry = new ReportedSqlExceptionRegistry();
        $registry->markReported($driverException);

        $handler = new BeforeSendHandler(
            ['enabled' => true, 'ignore_pure_access_denied' => true, 'deduplicate_sql_exceptions' => false],
            $registry,
        );
        $event = Event::createEvent();
        $hint  = EventHint::fromArray(['exception' => $exception]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testReturnsEventWhenHandlerDisabled(): void
    {
        $handler = new BeforeSendHandler(['enabled' => false]);
        $event   = Event::createEvent();
        $hint    = EventHint::fromArray(['exception' => new AccessDeniedException('Denied')]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testKeepsEventWhenHintHasNoException(): void
    {
        $registry = new ReportedSqlExceptionRegistry();
        $handler  = new BeforeSendHandler(
            ['enabled' => true, 'ignore_pure_access_denied' => true, 'deduplicate_sql_exceptions' => true],
            $registry,
        );
        $event = Event::createEvent();
        $hint  = new EventHint();

        $this->assertSame($event, $handler($event, $hint));
    }
}

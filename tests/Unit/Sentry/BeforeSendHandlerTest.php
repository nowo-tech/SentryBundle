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

    public function testReturnsNullEventWhenHandlerDisabled(): void
    {
        $handler = new BeforeSendHandler(['enabled' => false]);

        $this->assertNull($handler(null, null));
    }

    public function testReturnsEventWhenEventIsNullAndHandlerEnabled(): void
    {
        $handler = new BeforeSendHandler(['enabled' => true, 'ignore_pure_access_denied' => true]);

        $this->assertNull($handler(null, null));
    }

    public function testKeepsSqlExceptionWhenNotYetMarkedReported(): void
    {
        $registry = new ReportedSqlExceptionRegistry();
        $handler  = new BeforeSendHandler([
            'enabled'                    => true,
            'ignore_pure_access_denied'  => true,
            'deduplicate_sql_exceptions' => true,
        ], $registry);
        $event    = Event::createEvent();
        $sqlError = new RuntimeException('SQLSTATE[42S22]: Unknown column');
        $hint     = EventHint::fromArray(['exception' => $sqlError]);

        $this->assertSame($event, $handler($event, $hint));
    }

    public function testDropsSqlExceptionAlreadyMarkedReportedIncludingTwigWrapper(): void
    {
        $registry = new ReportedSqlExceptionRegistry();
        $sqlError = new RuntimeException('SQLSTATE[42S22]: Unknown column');
        $registry->markReported($sqlError);

        $handler = new BeforeSendHandler([
            'enabled'                    => true,
            'ignore_pure_access_denied'  => true,
            'deduplicate_sql_exceptions' => true,
        ], $registry);
        $event = Event::createEvent();

        $this->assertNull($handler($event, EventHint::fromArray(['exception' => $sqlError])));
        $this->assertNull($handler($event, EventHint::fromArray([
            'exception' => new RuntimeException('Twig render failed', 0, $sqlError),
        ])));
    }
}

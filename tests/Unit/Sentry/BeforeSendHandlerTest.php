<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

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
}

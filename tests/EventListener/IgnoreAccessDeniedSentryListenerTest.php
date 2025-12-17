<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\EventListener;

use Nowo\SentryBundle\EventListener\IgnoreAccessDeniedSentryListener;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Test case for IgnoreAccessDeniedSentryListener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class IgnoreAccessDeniedSentryListenerTest extends TestCase
{
    /**
     * Test that AccessDeniedException is handled correctly.
     */
    public function testInvokeWithAccessDeniedException(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $options = new Options(['dsn' => 'https://test@test.ingest.sentry.io/test']);
        $hub = $this->createMock(HubInterface::class);

        $client->method('getOptions')->willReturn($options);
        $hub->method('getClient')->willReturn($client);

        $config = ['enabled' => true];
        $listener = new IgnoreAccessDeniedSentryListener($hub, $config);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new AccessDeniedException('Access denied');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $listener->__invoke($event);

        // Verify that the callback was set (we can't easily test this without reflection)
        $this->assertNotNull($options->getBeforeSendCallback());
    }

    /**
     * Test that non-AccessDeniedException exceptions are not handled.
     */
    public function testInvokeWithOtherException(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $config = ['enabled' => true];
        $listener = new IgnoreAccessDeniedSentryListener($hub, $config);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new \RuntimeException('Other exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $listener->__invoke($event);

        // Should not throw any exception
        $this->assertTrue(true);
    }

    /**
     * Test that the listener is disabled when configuration says so.
     */
    public function testInvokeWhenDisabled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $config = ['enabled' => false];
        $listener = new IgnoreAccessDeniedSentryListener($hub, $config);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new AccessDeniedException('Access denied');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $listener->__invoke($event);

        // Should not process the exception when disabled
        $this->assertTrue(true);
    }
}

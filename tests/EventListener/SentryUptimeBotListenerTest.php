<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\EventListener;

use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test case for SentryUptimeBotListener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class SentryUptimeBotListenerTest extends TestCase
{
    /**
     * Test that SentryUptimeBot requests are handled.
     */
    public function testOnKernelRequestWithSentryUptimeBot(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths' => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test that Uptime-Kuma requests are handled.
     */
    public function testOnKernelRequestWithUptimeKuma(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths' => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/');
        $request->headers->set('User-Agent', 'Uptime-Kuma/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test that kube-probe requests are handled.
     */
    public function testOnKernelRequestWithKubeProbe(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths' => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/login');
        $request->headers->set('User-Agent', 'kube-probe/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test that regular requests are not handled.
     */
    public function testOnKernelRequestWithRegularRequest(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths' => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that requests to non-monitored paths are not handled.
     */
    public function testOnKernelRequestWithNonMonitoredPath(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths' => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/api/test');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that the listener is disabled when configuration says so.
     */
    public function testOnKernelRequestWhenDisabled(): void
    {
        $config = ['enabled' => false];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that custom user agents and paths work.
     */
    public function testOnKernelRequestWithCustomConfig(): void
    {
        $config = [
            'enabled' => true,
            'user_agents' => ['MyCustomBot'],
            'paths' => ['/health', '/status'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request = Request::create('/health');
        $request->headers->set('User-Agent', 'MyCustomBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}

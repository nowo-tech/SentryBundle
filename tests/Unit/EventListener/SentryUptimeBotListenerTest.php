<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\EventListener;

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
 * @copyright 2026 Nowo.tech
 */
class SentryUptimeBotListenerTest extends TestCase
{
    /**
     * Test that SentryUptimeBot requests are handled.
     */
    public function testOnKernelRequestWithSentryUptimeBot(): void
    {
        $config = [
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths'       => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

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
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths'       => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/');
        $request->headers->set('User-Agent', 'Uptime-Kuma/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

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
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths'       => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/login');
        $request->headers->set('User-Agent', 'kube-probe/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

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
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths'       => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that requests to non-monitored paths are not handled.
     */
    public function testOnKernelRequestWithNonMonitoredPath(): void
    {
        $config = [
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'],
            'paths'       => ['/dashboard', '/', '/login'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/api/test');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that the listener is disabled when configuration says so.
     */
    public function testOnKernelRequestWhenDisabled(): void
    {
        $config   = ['enabled' => false];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/dashboard');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * Test that sub-paths of a configured path match on a segment boundary.
     */
    public function testOnKernelRequestWithPathPrefixMatch(): void
    {
        $config = [
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0'],
            'paths'       => ['/login', '/api/health'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/login/check');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertEquals(Response::HTTP_OK, $event->getResponse()->getStatusCode());
    }

    /**
     * Test that custom user agents and paths work.
     */
    public function testOnKernelRequestWithCustomConfig(): void
    {
        $config = [
            'enabled'     => true,
            'user_agents' => ['MyCustomBot'],
            'paths'       => ['/health', '/status'],
        ];
        $listener = new SentryUptimeBotListener($config);
        $request  = Request::create('/health');
        $request->headers->set('User-Agent', 'MyCustomBot/1.0');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Sibling paths sharing a prefix must not match: `/health` does not cover `/healthz-admin`.
     *
     * @dataProvider siblingPathProvider
     */
    public function testOnKernelRequestDoesNotMatchSiblingPathSharingPrefix(string $pathInfo): void
    {
        $listener = new SentryUptimeBotListener([
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0'],
            'paths'       => ['/health', '/status/'],
        ]);
        $request = Request::create($pathInfo);
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function siblingPathProvider(): iterable
    {
        yield 'healthz-admin' => ['/healthz-admin'];
        yield 'health-admin' => ['/health-admin'];
        yield 'statusboard' => ['/statusboard'];
    }

    /**
     * A configured path with a trailing slash still matches its sub-paths.
     */
    public function testOnKernelRequestMatchesSubPathOfPathWithTrailingSlash(): void
    {
        $listener = new SentryUptimeBotListener([
            'enabled'     => true,
            'user_agents' => ['SentryUptimeBot/1.0'],
            'paths'       => ['/status/'],
        ]);
        $request = Request::create('/status/db');
        $request->headers->set('User-Agent', 'SentryUptimeBot/1.0');

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
    }
}

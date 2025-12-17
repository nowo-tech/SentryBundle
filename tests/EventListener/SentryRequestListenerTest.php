<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\EventListener;

use Nowo\SentryBundle\EventListener\SentryRequestListener;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test case for SentryRequestListener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class SentryRequestListenerTest extends TestCase
{
    /**
     * Test that the listener configures Sentry scope with request information.
     */
    public function testOnKernelRequestConfiguresScope(): void
    {
        $scope = $this->createMock(Scope::class);
        $hub = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        $scope->expects($this->exactly(2))
            ->method('setTag')
            ->willReturnSelf();

        $config = ['enabled' => true, 'set_domain_tag' => true, 'set_environment_tag' => true, 'set_user_info' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener ignores sub-requests.
     */
    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $hub->expects($this->never())->method('configureScope');

        $config = ['enabled' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that user information is added to scope when user is authenticated.
     */
    public function testOnKernelRequestWithAuthenticatedUser(): void
    {
        $scope = $this->createMock(Scope::class);
        $hub = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);
        $user = $this->createMock(UserInterface::class);

        $user->method('getUserIdentifier')->willReturn('user123');
        $security->method('getUser')->willReturn($user);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        $scope->expects($this->exactly(2))
            ->method('setTag')
            ->willReturnSelf();

        $scope->expects($this->once())
            ->method('setUser')
            ->with($this->callback(function ($userData) {
                return isset($userData['id']) && $userData['id'] === 'user123';
            }))
            ->willReturnSelf();

        $config = ['enabled' => true, 'set_domain_tag' => true, 'set_environment_tag' => true, 'set_user_info' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener is disabled when configuration says so.
     */
    public function testOnKernelRequestWhenDisabled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->never())->method('configureScope');

        $config = ['enabled' => false];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener respects configuration options.
     */
    public function testOnKernelRequestRespectsConfiguration(): void
    {
        $scope = $this->createMock(Scope::class);
        $hub = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        // Only domain tag should be set, not environment tag
        $scope->expects($this->once())
            ->method('setTag')
            ->with('domain', 'example.com')
            ->willReturnSelf();

        $scope->expects($this->never())
            ->method('setUser');

        $config = [
            'enabled' => true,
            'set_domain_tag' => true,
            'set_environment_tag' => false,
            'set_user_info' => false,
            'set_session_id' => false,
        ];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }
}

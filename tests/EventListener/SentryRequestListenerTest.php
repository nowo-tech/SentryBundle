<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\EventListener;

use Exception;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as BaseRequest;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test case for SentryRequestListener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class SentryRequestListenerTest extends TestCase
{
    /**
     * Test that the listener configures Sentry scope with request information.
     */
    public function testOnKernelRequestConfiguresScope(): void
    {
        $scope    = $this->createMock(Scope::class);
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(static function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        $scope->expects($this->exactly(2))
            ->method('setTag')
            ->willReturnSelf();

        $config   = ['enabled' => true, 'set_domain_tag' => true, 'set_environment_tag' => true, 'set_user_info' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener ignores sub-requests.
     */
    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $hub->expects($this->never())->method('configureScope');

        $config   = ['enabled' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that user information is added to scope when user is authenticated.
     */
    public function testOnKernelRequestWithAuthenticatedUser(): void
    {
        $scope    = $this->createMock(Scope::class);
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);
        $user     = $this->createMock(UserInterface::class);

        $user->method('getUserIdentifier')->willReturn('user123');
        $security->method('getUser')->willReturn($user);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(static function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        $scope->expects($this->exactly(2))
            ->method('setTag')
            ->willReturnSelf();

        $scope->expects($this->once())
            ->method('setUser')
            ->with($this->callback(static function ($userData) {
                return isset($userData['id']) && $userData['id'] === 'user123';
            }))
            ->willReturnSelf();

        $config   = ['enabled' => true, 'set_domain_tag' => true, 'set_environment_tag' => true, 'set_user_info' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener is disabled when configuration says so.
     */
    public function testOnKernelRequestWhenDisabled(): void
    {
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->never())->method('configureScope');

        $config   = ['enabled' => false];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener respects configuration options.
     */
    public function testOnKernelRequestRespectsConfiguration(): void
    {
        $scope    = $this->createMock(Scope::class);
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(static function ($callback) use ($scope) {
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
            'enabled'             => true,
            'set_domain_tag'      => true,
            'set_environment_tag' => false,
            'set_user_info'       => false,
            'set_session_id'      => false,
        ];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that the listener does nothing when hub is null.
     */
    public function testOnKernelRequestWithNullHub(): void
    {
        $security = $this->createMock(Security::class);
        $request  = Request::create('https://example.com/test');
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $config   = ['enabled' => true];
        $listener = new SentryRequestListener(null, $config, 'test', $security);
        $listener->onKernelRequest($event);

        $this->assertTrue(true);
    }

    /**
     * Test that the listener adds session id when session is started.
     */
    public function testOnKernelRequestWithSession(): void
    {
        $scope    = $this->createMock(Scope::class);
        $hub      = $this->createMock(HubInterface::class);
        $security = $this->createMock(Security::class);
        $session  = $this->createMock(SessionInterface::class);
        $session->method('isStarted')->willReturn(true);
        $session->method('getId')->willReturn('sess-123');

        $request = Request::create('https://example.com/test');
        $request->setSession($session);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(static function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));

        $scope->expects($this->exactly(2))->method('setTag')->willReturnSelf();
        $scope->expects($this->once())
            ->method('setExtra')
            ->with('session_id', 'sess-123')
            ->willReturnSelf();

        $config   = ['enabled' => true, 'set_domain_tag' => true, 'set_environment_tag' => true, 'set_user_info' => false, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);
    }

    /**
     * Test that when request hasSession/getSession throws RuntimeException, session is null and listener continues.
     */
    public function testOnKernelRequestWhenGetSessionThrows(): void
    {
        $scope = $this->createMock(Scope::class);
        $hub   = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('configureScope')->with($this->callback(static function ($cb) use ($scope) {
            $cb($scope);

            return true;
        }));
        $security = $this->createMock(Security::class);

        $request = new class extends BaseRequest {
            public function hasSession(bool $skipIfUninitialized = false): bool
            {
                return true;
            }

            public function getSession(): SessionInterface
            {
                throw new RuntimeException('Session unavailable');
            }
        };
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $config   = ['enabled' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);

        $this->assertTrue(true);
    }

    /**
     * Test that when getSession throws an exception (e.g. RedisException), session is null and listener continues.
     * Uses RuntimeException to avoid requiring the Redis stub in all CI environments; the listener catches both.
     */
    public function testOnKernelRequestWhenGetSessionThrowsRedisException(): void
    {
        $scope = $this->createMock(Scope::class);
        $hub   = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('configureScope')->with($this->callback(static function ($cb) use ($scope) {
            $cb($scope);

            return true;
        }));
        $security = $this->createMock(Security::class);

        $request = new class extends BaseRequest {
            public function hasSession(bool $skipIfUninitialized = false): bool
            {
                return true;
            }

            public function getSession(): SessionInterface
            {
                throw new RuntimeException('Session unavailable (e.g. Redis)');
            }
        };
        $request->headers->set('Host', 'example.com');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $config   = ['enabled' => true, 'set_session_id' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);

        $this->assertTrue(true);
    }

    /**
     * Test that when configureScope throws, listener does not propagate.
     */
    public function testOnKernelRequestWhenConfigureScopeThrows(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('configureScope')->willThrowException(new Exception('Sentry error'));
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $config   = ['enabled' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);

        $this->assertTrue(true);
    }

    /**
     * Test that when scope callback throws (e.g. setTag), listener does not propagate.
     */
    public function testOnKernelRequestWhenScopeCallbackThrows(): void
    {
        $scope = $this->createMock(Scope::class);
        $scope->method('setTag')->willThrowException(new RuntimeException('Sentry scope error'));
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(static function ($callback) use ($scope) {
                $callback($scope);

                return true;
            }));
        $security = $this->createMock(Security::class);

        $request = Request::create('https://example.com/test');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $config   = ['enabled' => true, 'set_domain_tag' => true];
        $listener = new SentryRequestListener($hub, $config, 'test', $security);
        $listener->onKernelRequest($event);

        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\EventListener;

use Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Test case for SubRequestAccessDeniedContextListener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class SubRequestAccessDeniedContextListenerTest extends TestCase
{
    public function testEnrichesScopeWhenSubRequestAccessDeniedBreaksParentPage(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(static function (callable $callback): void {
                $scope = new Scope();
                $callback($scope);
            });

        $parentRequest = Request::create('/parent', 'GET', [], [], [], ['HTTP_HOST' => 'example.test']);
        $parentRequest->attributes->set('_route', 'parent_route');
        $parentRequest->attributes->set('_controller', 'App\\Controller\\ParentController');

        $requestStack = new RequestStack();
        $requestStack->push($parentRequest);
        $request = Request::create('/fragment', 'GET', [], [], [], ['HTTP_HOST' => 'example.test']);
        $request->attributes->set('_route', 'fragment_route');
        $request->attributes->set('_controller', 'App\\Controller\\FragmentController');
        $requestStack->push($request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $wrapped  = new RuntimeException('Template rendering failed', 0, new AccessDeniedException('Denied'));
        $event    = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapped);

        $listener->__invoke($event);
    }

    public function testIgnoresPureMainRequestAccessDenied(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('configureScope');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $request = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new AccessDeniedException('Denied'),
        );

        $listener->__invoke($event);
    }

    public function testIgnoresIsolatedSubRequestAccessDenied(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('configureScope');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $requestStack->push(Request::create('/fragment'));
        $request = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST,
            new AccessDeniedException('Denied'),
        );

        $listener->__invoke($event);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('configureScope');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $request = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => false]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $wrapped  = new RuntimeException('Template rendering failed', 0, new AccessDeniedException('Denied'));
        $event    = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapped);

        $listener->__invoke($event);
    }

    public function testDoesNothingWhenSentryHubIsNull(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $request = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        $listener = new SubRequestAccessDeniedContextListener(null, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $wrapped  = new RuntimeException('Template rendering failed', 0, new AccessDeniedException('Denied'));
        $event    = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapped);

        $listener->__invoke($event);

        $this->addToAssertionCount(1);
    }

    public function testIgnoresRuntimeExceptionWithoutAccessDeniedInChain(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('configureScope');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $request = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new RuntimeException('Unrelated failure'),
        );

        $listener->__invoke($event);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\EventListener;

use Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\State\HubInterface;
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
                $scope = new class {
                    /** @var array<string, string> */
                    public array $tags = [];

                    /** @var array<string, mixed> */
                    public array $extras = [];

                    public function setTag(string $key, string $value): void
                    {
                        $this->tags[$key] = $value;
                    }

                    public function setExtra(string $key, mixed $value): void
                    {
                        $this->extras[$key] = $value;
                    }
                };
                $callback($scope);
            });

        $requestStack = new RequestStack();
        $parent       = Request::create('/parent');
        $parent->attributes->set('_route', 'parent_route');
        $requestStack->push($parent);
        $request = Request::create('/child');
        $request->attributes->set('_route', 'child_route');
        $requestStack->push($request);

        $listener = new SubRequestAccessDeniedContextListener($hub, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $wrapped  = new RuntimeException('Template rendering failed', 0, new AccessDeniedException('Denied'));
        $event    = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapped);

        $listener->__invoke($event);
    }

    public function testIgnoresWhenHubIsNull(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $listener = new SubRequestAccessDeniedContextListener(null, $requestStack, ['enabled' => true]);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $wrapped  = new RuntimeException('Template rendering failed', 0, new AccessDeniedException('Denied'));
        $request  = $requestStack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapped);

        $listener->__invoke($event);

        $this->addToAssertionCount(1);
    }

    public function testIgnoresWhenExceptionChainHasNoAccessDenied(): void
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
            new RuntimeException('Other failure'),
        );

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
}

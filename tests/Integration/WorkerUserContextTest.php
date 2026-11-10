<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Integration;

use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\Tests\Kernel\TestKernel;
use Nowo\SentryBundle\Tests\Unit\User\IdUser;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function gc_collect_cycles;
use function hash;
use function memory_get_usage;

/**
 * FrankenPHP worker mode without kernel reset (FRANKENPHP_RESET_KERNEL=false): one booted kernel, the same hub,
 * provider and listener instances serve many requests and the services resetter never runs. The Sentry user of a
 * request must be exactly that request's user: never the previous one, never kept for an anonymous request.
 */
final class WorkerUserContextTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testDefaultProviderIsWiredAndAliased(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(DefaultSentryUserContextProvider::class, $container->get(SentryUserContextProviderInterface::class));
        self::assertInstanceOf(SentryRequestListener::class, $container->get(SentryRequestListener::class));
    }

    public function testUserAThenUserBThenAnonymousNeverLeakAcrossRequestsInOneKernel(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $hub       = $container->get(HubInterface::class);
        $provider  = $container->get(SentryUserContextProviderInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);
        self::assertInstanceOf(SentryUserContextProviderInterface::class, $provider);

        $alice = new IdUser(41, 'alice@example.com');
        $bob   = new InMemoryUser('bob@example.com', null);
        $users = [[$alice, '41'], [$bob, hash('sha256', 'bob@example.com')], [null, null]];

        // The test kernel has no SecurityBundle: a real Security over a real token storage plays the firewall (one
        // token per request, none for the anonymous one), while the hub and the provider are the kernel's shared
        // services, as on a FrankenPHP worker.
        $tokenStorage = new TokenStorage();
        $security     = new Security(new ServiceLocator(['security.token_storage' => static fn (): TokenStorage => $tokenStorage]));
        $listener     = new SentryRequestListener($hub, ['set_user_info' => true, 'set_session_id' => false], 'prod', $security, $provider);
        $kernel       = $this->createStub(HttpKernelInterface::class);

        $baseline = 0;
        for ($i = 0; $i < 3000; ++$i) {
            [$user, $expectedId] = $users[$i % 3];
            $tokenStorage->setToken($user instanceof UserInterface ? new UsernamePasswordToken($user, 'main') : null);
            $listener->onKernelRequest(new RequestEvent($kernel, Request::create('https://example.com/'), HttpKernelInterface::MAIN_REQUEST));

            $seen = 'unset';
            $hub->configureScope(static function (Scope $scope) use (&$seen): void {
                $seen = $scope->getUser()?->getId();
            });
            self::assertSame($expectedId, $seen, 'request ' . $i);
            self::assertNotSame('alice@example.com', $seen);
            self::assertNotSame('bob@example.com', $seen);

            if ($i === 299) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        // Flat memory: no per-request state accumulates in the listener, provider or hub scope.
        self::assertLessThan(256 * 1024, memory_get_usage() - $baseline);
    }
}

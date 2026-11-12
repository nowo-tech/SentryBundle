<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Integration\Ddd;

use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\Tests\Fixtures\Ports\IncidentReporter;
use Nowo\SentryBundle\Tests\Fixtures\Ports\OpaqueIdProvider;
use Nowo\SentryBundle\Tests\Kernel\PortsTestKernel;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function gc_collect_cycles;
use function hash;
use function memory_get_usage;
use function uniqid;

/**
 * 1.13 (REQ-ARCH-001): a DDD / hexagonal host that uses **only** the framework-free ports: it aliases
 * `UserContextProviderInterface` to its own provider (an opaque member id, never the e-mail) and reports through its own
 * incident adapter over `ErrorReporterInterface`. The bundle's request listener must use the host provider. Then one
 * kept-alive kernel serves 2,000 requests as two users and an anonymous visitor in alternation (REQ-RUNTIME-002).
 */
final class DddHostPortsKernelTest extends TestCase
{
    private ?PortsTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(PortsTestKernel::baseDir());
    }

    public function testOnlyTheNewPortsDriveTheUserContextAndTheReporter(): void
    {
        $this->kernel = new PortsTestKernel(uniqid('ddd', true), ['request_listener' => ['set_session_id' => false]], static function (ContainerConfigurator $container): void {
            $services = $container->services()->defaults()->autowire()->autoconfigure();
            $services->set(OpaqueIdProvider::class);
            $services->alias(UserContextProviderInterface::class, OpaqueIdProvider::class);
            $services->set(IncidentReporter::class)->public();
        }, false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $reporter = $container->get(IncidentReporter::class);
        self::assertInstanceOf(IncidentReporter::class, $reporter);
        self::assertTrue($reporter->incident(new RuntimeException('payment gateway down'), 'billing'), 'accepted by the hub (no DSN: nothing leaves the process)');

        $listener = $container->get(SentryRequestListener::class);
        self::assertInstanceOf(SentryRequestListener::class, $listener);
        $provider = (new ReflectionProperty($listener, 'userContextProvider'))->getValue($listener);
        self::assertInstanceOf(OpaqueIdProvider::class, $provider, 'the host aliased only the new port: the listener uses it');
        $hub = $container->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);

        // No SecurityBundle in this kernel: a real Security over one token storage plays the firewall.
        $tokens   = new TokenStorage();
        $security = new Security(new ServiceLocator(['security.token_storage' => static fn (): TokenStorage => $tokens]));
        $worker   = new SentryRequestListener($hub, ['set_user_info' => true, 'set_session_id' => false], 'prod', $security, $provider);
        $users    = [
            [new InMemoryUser('alice@example.com', null), 'member-' . hash('crc32b', 'alice@example.com')],
            [new InMemoryUser('bob@example.com', null), 'member-' . hash('crc32b', 'bob@example.com')],
            [null, null],
        ];

        $baseline = 0;
        for ($i = 0; $i < 2000; ++$i) {
            [$user, $expected] = $users[$i % 3];
            $tokens->setToken($user === null ? null : new UsernamePasswordToken($user, 'main'));
            $worker->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('https://example.com/'), HttpKernelInterface::MAIN_REQUEST));

            $seen = 'unset';
            $hub->configureScope(static function (Scope $scope) use (&$seen): void {
                $seen = $scope->getUser()?->getId();
            });
            self::assertSame($expected, $seen, 'request ' . $i);
            if ($i === 199) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(256 * 1024, memory_get_usage() - $baseline, 'memory stays flat');
    }
}

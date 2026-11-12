<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Integration;

use Closure;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\Service\ErrorReporterInterface;
use Nowo\SentryBundle\Service\SentryErrorReporter;
use Nowo\SentryBundle\Tests\Fixtures\Ports\DualProvider;
use Nowo\SentryBundle\Tests\Fixtures\Ports\LegacyProvider;
use Nowo\SentryBundle\Tests\Fixtures\Ports\NewPortDecorator;
use Nowo\SentryBundle\Tests\Fixtures\Ports\OpaqueIdProvider;
use Nowo\SentryBundle\Tests\Kernel\PortsTestKernel;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function uniqid;

/**
 * 1.13: `lint:container` (Symfony's CheckAliasValidityPass included) passes with the user context provider on the
 * framework-free port only, on the deprecated port only, on both, configured by service id, and as a new-port-only
 * decorator of the default provider. Each interface alias exists only when its service implements it, and the request
 * listener uses the service the host chose.
 */
final class PortsLintContainerKernelTest extends TestCase
{
    private ?PortsTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(PortsTestKernel::baseDir());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, Closure(ContainerConfigurator): void, class-string, bool, bool}>
     */
    public static function hostPorts(): iterable
    {
        yield 'default provider' => [[], static function (): void {}, DefaultSentryUserContextProvider::class, true, true];
        yield 'new port alias only' => [[], self::alias(OpaqueIdProvider::class, UserContextProviderInterface::class), OpaqueIdProvider::class, true, true];
        yield 'deprecated port alias only' => [[], self::alias(LegacyProvider::class, SentryUserContextProviderInterface::class), LegacyProvider::class, true, true];
        yield 'both aliases to a dual service' => [[], self::alias(DualProvider::class, SentryUserContextProviderInterface::class, UserContextProviderInterface::class), DualProvider::class, true, true];
        yield 'configured new-port service' => [['request_listener' => ['user_context_provider' => OpaqueIdProvider::class]], self::alias(OpaqueIdProvider::class), OpaqueIdProvider::class, true, true];
        yield 'configured deprecated-port service' => [['request_listener' => ['user_context_provider' => LegacyProvider::class]], self::alias(LegacyProvider::class), LegacyProvider::class, true, true];
        yield 'new-port decorator of the default provider' => [[], self::alias(NewPortDecorator::class), NewPortDecorator::class, false, true];
    }

    /**
     * @param array<string, mixed> $config
     * @param Closure(ContainerConfigurator): void $services
     * @param class-string $expected
     */
    #[DataProvider('hostPorts')]
    public function testLintContainerPassesAndTheListenerUsesTheHostProvider(array $config, Closure $services, string $expected, bool $hasLegacy, bool $hasNew): void
    {
        $this->kernel = new PortsTestKernel(uniqid('lint', true), $config, $services);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));
        self::assertSame(0, $tester->execute([]), $tester->getDisplay());
        self::assertStringContainsString('linted successfully', $tester->getDisplay());

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        // Unused private aliases are removed when the container is compiled: only a dropped alias is observable here.
        if (!$hasLegacy) {
            self::assertFalse($container->has(SentryUserContextProviderInterface::class), 'the deprecated alias is dropped');
        }
        if (!$hasNew) {
            self::assertFalse($container->has(UserContextProviderInterface::class), 'the new alias is dropped');
        }
        self::assertInstanceOf(SentryErrorReporter::class, $container->get(ErrorReporterInterface::class), 'rule 1: the reporter port');

        $listener = $container->get(SentryRequestListener::class);
        self::assertInstanceOf(SentryRequestListener::class, $listener);
        self::assertInstanceOf($expected, (new ReflectionProperty($listener, 'userContextProvider'))->getValue($listener));
    }

    /**
     * @param class-string $class
     * @param class-string ...$aliases
     *
     * @return Closure(ContainerConfigurator): void
     */
    private static function alias(string $class, string ...$aliases): Closure
    {
        return static function (ContainerConfigurator $container) use ($class, $aliases): void {
            $services = $container->services()->defaults()->autowire()->autoconfigure();
            $services->set($class);
            foreach ($aliases as $alias) {
                $services->alias($alias, $class);
            }
        };
    }
}

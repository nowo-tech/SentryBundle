<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\SentryBundle\DependencyInjection\Compiler\UserContextProvidersPass;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\Tests\Fixtures\Ports\DualProvider;
use Nowo\SentryBundle\Tests\Fixtures\Ports\LegacyProvider;
use Nowo\SentryBundle\Tests\Fixtures\Ports\NotAProvider;
use Nowo\SentryBundle\Tests\Fixtures\Ports\OpaqueIdProvider;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

final class UserContextProvidersPassTest extends TestCase
{
    public function testDefaultsKeepTheDefaultProviderAndBothAliases(): void
    {
        $container = $this->container();
        (new UserContextProvidersPass())->process($container);

        self::assertSame(DefaultSentryUserContextProvider::class, $this->chosen($container));
        self::assertTrue($container->hasAlias(SentryUserContextProviderInterface::class));
        self::assertTrue($container->hasAlias(UserContextProviderInterface::class));
    }

    public function testAHostAliasOnTheNewPortOnlyIsUsed(): void
    {
        $container = $this->container(new: OpaqueIdProvider::class);
        self::assertSame([], $this->deprecations(static fn () => (new UserContextProvidersPass())->process($container)));
        self::assertSame(OpaqueIdProvider::class, $this->chosen($container));
    }

    public function testAHostAliasOnTheDeprecatedPortOnlyIsUsedAndDeprecated(): void
    {
        $container    = $this->container(legacy: LegacyProvider::class);
        $deprecations = $this->deprecations(static fn () => (new UserContextProvidersPass())->process($container));

        self::assertSame(LegacyProvider::class, $this->chosen($container));
        self::assertCount(1, $deprecations);
        self::assertStringContainsString('Since nowo-tech/sentry-bundle 1.13: The user context provider "' . LegacyProvider::class . '"', $deprecations[0]);
        self::assertTrue($container->hasAlias(SentryUserContextProviderInterface::class));
        self::assertTrue($container->hasAlias(UserContextProviderInterface::class), 'the new alias still points to the default provider');
    }

    public function testBothAliasesToOneDualServiceUseIt(): void
    {
        $container = $this->container(legacy: DualProvider::class, new: DualProvider::class);
        (new UserContextProvidersPass())->process($container);
        self::assertSame(DualProvider::class, $this->chosen($container));
    }

    public function testTwoDifferentHostServicesFailTheBuild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot choose the user context provider');
        (new UserContextProvidersPass())->process($this->container(legacy: LegacyProvider::class, new: OpaqueIdProvider::class));
    }

    public function testTheConfiguredServiceWinsOverTheAliases(): void
    {
        $container = $this->container(legacy: LegacyProvider::class, new: OpaqueIdProvider::class);
        $container->setDefinition('app.provider', new Definition(DualProvider::class));
        $container->setParameter(UserContextProvidersPass::CONFIGURED_PARAMETER, 'app.provider');
        (new UserContextProvidersPass())->process($container);

        self::assertSame('app.provider', $this->chosen($container));
        self::assertFalse($container->hasParameter(UserContextProvidersPass::CONFIGURED_PARAMETER), 'the hidden parameter is removed');
    }

    public function testAConfiguredServiceOnNoPortFailsTheBuild(): void
    {
        $container = $this->container();
        $container->setDefinition('app.provider', new Definition(NotAProvider::class));
        $container->setParameter(UserContextProvidersPass::CONFIGURED_PARAMETER, 'app.provider');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the user context provider "app.provider" (' . NotAProvider::class . ') must implement');
        (new UserContextProvidersPass())->process($container);
    }

    public function testAnAliasToAServiceOnNoPortFailsTheBuild(): void
    {
        $container = $this->container();
        $container->removeDefinition(SentryRequestListener::class);
        $container->setDefinition('app.bad', new Definition(NotAProvider::class));
        $container->setAlias(UserContextProviderInterface::class, 'app.bad');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement');
        (new UserContextProvidersPass())->process($container);
    }

    /**
     * A new-port-only service behind both aliases (a decorator of the default provider): the deprecated alias is
     * dropped, so CheckAliasValidityPass accepts the container.
     */
    public function testAnAliasWhoseServiceDoesNotImplementItIsDropped(): void
    {
        $container = $this->container();
        $container->setDefinition('app.decorator', new Definition(OpaqueIdProvider::class));
        $container->setAlias(DefaultSentryUserContextProvider::class, 'app.decorator');
        (new UserContextProvidersPass())->process($container);

        self::assertSame('app.decorator', $this->chosen($container));
        self::assertFalse($container->hasAlias(SentryUserContextProviderInterface::class));
        self::assertTrue($container->hasAlias(UserContextProviderInterface::class));
    }

    /**
     * Services whose class cannot be known (factory, synthetic, unknown class) keep their alias and are left to the
     * listener's typed constructor; without the listener only the aliases are checked.
     */
    public function testUnknownClassesAreLeftToRuntime(): void
    {
        $container = $this->container();
        $container->setDefinition('app.factory', (new Definition(stdClass::class))->setFactory('make'));
        $container->setDefinition('app.synthetic', (new Definition(stdClass::class))->setSynthetic(true));
        $container->setAlias(SentryUserContextProviderInterface::class, 'app.factory');
        $container->setAlias(UserContextProviderInterface::class, 'app.factory');
        (new UserContextProvidersPass())->process($container);
        self::assertSame('app.factory', $this->chosen($container));

        $synthetic = new ContainerBuilder();
        $synthetic->setDefinition('app.synthetic', (new Definition(stdClass::class))->setSynthetic(true));
        $synthetic->setDefinition('App\\Unknown', new Definition());
        $synthetic->setAlias(SentryUserContextProviderInterface::class, 'app.synthetic');
        $synthetic->setAlias(UserContextProviderInterface::class, 'App\\Unknown');
        (new UserContextProvidersPass())->process($synthetic);
        self::assertTrue($synthetic->hasAlias(UserContextProviderInterface::class));

        (new UserContextProvidersPass())->process(new ContainerBuilder());
    }

    public function testAPositionalArgumentIsReplacedToo(): void
    {
        $container = $this->container(new: OpaqueIdProvider::class);
        $container->getDefinition(SentryRequestListener::class)->setArguments([null, [], 'prod', null, new Reference(SentryUserContextProviderInterface::class)]);
        (new UserContextProvidersPass())->process($container);

        $argument = $container->getDefinition(SentryRequestListener::class)->getArgument(4);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(OpaqueIdProvider::class, (string) $argument);
    }

    /**
     * @param class-string|null $legacy
     * @param class-string|null $new
     */
    private function container(?string $legacy = null, ?string $new = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DefaultSentryUserContextProvider::class, new Definition(DefaultSentryUserContextProvider::class));
        $container->setDefinition(SentryRequestListener::class, new Definition(SentryRequestListener::class, ['$userContextProvider' => new Reference(SentryUserContextProviderInterface::class)]));
        $container->setAlias(SentryUserContextProviderInterface::class, DefaultSentryUserContextProvider::class);
        $container->setAlias(UserContextProviderInterface::class, DefaultSentryUserContextProvider::class);
        foreach ([SentryUserContextProviderInterface::class => $legacy, UserContextProviderInterface::class => $new] as $alias => $class) {
            if ($class !== null) {
                if (!$container->hasDefinition($class)) {
                    $container->setDefinition($class, new Definition($class));
                }
                $container->setAlias($alias, $class);
            }
        }

        return $container;
    }

    private function chosen(ContainerBuilder $container): string
    {
        $arguments = $container->getDefinition(SentryRequestListener::class)->getArguments();
        $argument  = $arguments['$userContextProvider'] ?? null;
        self::assertInstanceOf(Reference::class, $argument);

        return (string) $argument;
    }

    /**
     * @param callable(): void $run
     *
     * @return list<string>
     */
    private function deprecations(callable $run): array
    {
        $messages = [];
        set_error_handler(static function (int $type, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $run();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }
}

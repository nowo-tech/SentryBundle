<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function class_exists;
use function is_a;
use function is_string;
use function sprintf;
use function trigger_deprecation;

/**
 * Wires the request listener to the user context provider the host actually chose (since 1.13, REQ-ARCH-001 rule 3).
 *
 * The provider has two aliases: the deprecated `SentryUserContextProviderInterface` (`UserInterface`) and the
 * framework-free `UserContextProviderInterface` (`UserRef`); both point to {@see DefaultSentryUserContextProvider}.
 * A host may alias or decorate only one of them. After decoration:
 *
 * 1. `request_listener.user_context_provider` is set: that service (as in 1.12);
 * 2. both aliases resolve to the same service: that service;
 * 3. only the new alias was changed: the new one (also when it is decorated with `#[AsDecorator]`);
 * 4. only the deprecated alias was changed: the deprecated one (as in 1.12, with a build-time deprecation);
 * 5. both were changed, to different services: the container build fails, so the user data sent is never chosen
 *    silently.
 *
 * The chosen service must implement one of the two interfaces (a service implementing both is called through the new
 * one; one implementing only the deprecated interface triggers a deprecation). Then each alias is kept only when the
 * class of the service it resolves to implements that interface, so `lint:container` (CheckAliasValidityPass) accepts a
 * host service on one port only (e.g. a decorator of the default provider that implements only the new interface). A
 * service whose class cannot be known (factory, synthetic, unknown class) keeps its alias, as CheckAliasValidityPass
 * does. Runs before the removing passes, while the aliases still exist and after DecoratorServicePass.
 */
final class UserContextProvidersPass implements CompilerPassInterface
{
    /** Service id of `request_listener.user_context_provider` (hidden parameter set by the extension, removed here). */
    public const CONFIGURED_PARAMETER = '.nowo_sentry.user_context_provider_id';

    private const LEGACY = SentryUserContextProviderInterface::class;

    private const NEW = UserContextProviderInterface::class;

    /**
     * @throws LogicException When the two aliases point to two different host services, or when the chosen provider
     *                        or an alias target implements neither interface
     */
    public function process(ContainerBuilder $container): void
    {
        $configured = null;
        if ($container->hasParameter(self::CONFIGURED_PARAMETER)) {
            $configured = $container->getParameter(self::CONFIGURED_PARAMETER);
            $container->getParameterBag()->remove(self::CONFIGURED_PARAMETER);
        }

        if ($container->hasDefinition(SentryRequestListener::class) && $container->hasAlias(self::LEGACY) && $container->hasAlias(self::NEW)) {
            $chosen = is_string($configured) && $configured !== '' ? $this->resolve($container, $configured) : $this->choose($container);
            $this->check($container, $chosen);
            $this->replace($container->getDefinition(SentryRequestListener::class), new Reference($chosen));
        }

        // After the choice above (which needs both aliases).
        foreach ([self::LEGACY, self::NEW] as $port) {
            $this->keepAliasOnlyIfImplemented($container, $port);
        }
    }

    private function choose(ContainerBuilder $container): string
    {
        $default = $this->resolve($container, DefaultSentryUserContextProvider::class);
        $legacy  = $this->resolve($container, self::LEGACY);
        $new     = $this->resolve($container, self::NEW);

        if ($legacy === $new || $legacy === $default) {
            return $new;
        }
        if ($new === $default) {
            return $legacy;
        }

        throw new LogicException(sprintf('NowoSentryBundle cannot choose the user context provider between "%s" (aliased to "%s") and "%s" (aliased to "%s"): alias one service to both, only the new interface, or set request_listener.user_context_provider.', self::LEGACY, $legacy, self::NEW, $new));
    }

    private function check(ContainerBuilder $container, string $id): void
    {
        $class = $this->serviceClass($container, $id);
        if ($class === null || is_a($class, self::NEW, true)) {
            return;
        }
        if (!is_a($class, self::LEGACY, true)) {
            throw new LogicException(sprintf('NowoSentryBundle: the user context provider "%s" (%s) must implement "%s".', $id, $class, self::NEW));
        }

        trigger_deprecation('nowo-tech/sentry-bundle', '1.13', 'The user context provider "%s" (%s) implements only "%s"; implement "%s" instead (the deprecated interface is removed in 2.0).', $id, $class, self::LEGACY, self::NEW);
    }

    /**
     * @param class-string $port
     */
    private function keepAliasOnlyIfImplemented(ContainerBuilder $container, string $port): void
    {
        if (!$container->hasAlias($port)) {
            return;
        }

        $id    = $this->resolve($container, $port);
        $class = $this->serviceClass($container, $id);
        if ($class === null || is_a($class, $port, true)) {
            return;
        }
        if (!is_a($class, self::LEGACY, true) && !is_a($class, self::NEW, true)) {
            throw new LogicException(sprintf('NowoSentryBundle: the user context provider "%s" (%s) must implement "%s".', $id, $class, self::NEW));
        }

        $container->removeAlias($port);
    }

    /** @return class-string|null null when the class cannot be known before the container is compiled */
    private function serviceClass(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }

        $definition = $container->getDefinition($id);
        if ($definition->getFactory() !== null || $definition->isSynthetic()) {
            return null;
        }

        // A definition without a class uses its id as the class (as ResolveClassPass does).
        $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

        return is_string($class) && class_exists($class) ? $class : null;
    }

    /**
     * Sets the listener's provider argument, by name before ResolveNamedArgumentsPass or by position after it.
     */
    private function replace(Definition $listener, Reference $reference): void
    {
        if (array_key_exists('$userContextProvider', $listener->getArguments())) {
            $listener->setArgument('$userContextProvider', $reference);

            return;
        }
        foreach ((new ReflectionMethod(SentryRequestListener::class, '__construct'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'userContextProvider') {
                $listener->setArgument($parameter->getPosition(), $reference);

                return;
            }
        }
    }

    /**
     * Follows aliases to the service id that is defined.
     */
    private function resolve(ContainerBuilder $container, string $id): string
    {
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }

        return $id;
    }
}

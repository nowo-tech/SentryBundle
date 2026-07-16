<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\DependencyInjection\Compiler;

use Nowo\SentryBundle\Sentry\BeforeSendChain;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function in_array;
use function is_array;
use function is_string;

/**
 * Ensures the bundle before_send handler is registered, chaining when the app
 * already configured sentry.options.before_send.
 */
final class BeforeSendChainPass implements CompilerPassInterface
{
    public const HANDLER_ID = 'nowo_sentry.before_send_handler';

    public const CHAIN_ID = 'nowo_sentry.before_send_chain';

    private const OPTIONS_ID = 'sentry.client.options';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::HANDLER_ID) || !$container->hasDefinition(self::OPTIONS_ID)) {
            return;
        }

        if (!$container->hasParameter('nowo_sentry.before_send_handler')) {
            return;
        }

        /** @var array<string, mixed> $config */
        $config = $container->getParameter('nowo_sentry.before_send_handler');
        if (!($config['register_automatically'] ?? true)) {
            return;
        }

        $optionsDefinition = $container->getDefinition(self::OPTIONS_ID);
        $options           = $optionsDefinition->getArgument(0);
        if (!is_array($options)) {
            return;
        }

        $existingId = $this->resolveServiceId($options['before_send'] ?? null);

        if (in_array($existingId, [null, self::HANDLER_ID, self::CHAIN_ID], true)) {
            $options['before_send'] = new Reference(self::HANDLER_ID);
            $optionsDefinition->setArgument(0, $options);

            return;
        }

        $chain = new Definition(BeforeSendChain::class);
        $chain->setArguments([
            new Reference(self::HANDLER_ID),
            new Reference($existingId),
        ]);
        $chain->setPublic(false);
        $container->setDefinition(self::CHAIN_ID, $chain);

        $options['before_send'] = new Reference(self::CHAIN_ID);
        $optionsDefinition->setArgument(0, $options);
    }

    private function resolveServiceId(mixed $value): ?string
    {
        if ($value instanceof Reference) {
            return (string) $value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}

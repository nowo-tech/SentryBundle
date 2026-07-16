<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\SentryBundle\DependencyInjection\Compiler\BeforeSendChainPass;
use Nowo\SentryBundle\Sentry\BeforeSendChain;
use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BeforeSendChainPassTest extends TestCase
{
    public function testSetsHandlerWhenBeforeSendMissing(): void
    {
        $container = $this->createContainerWithOptions([]);
        $container->setParameter('nowo_sentry.before_send_handler', [
            'enabled'                => true,
            'register_automatically' => true,
        ]);
        $container->setDefinition(BeforeSendChainPass::HANDLER_ID, new Definition());

        (new BeforeSendChainPass())->process($container);

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertInstanceOf(Reference::class, $options['before_send']);
        $this->assertSame(BeforeSendChainPass::HANDLER_ID, (string) $options['before_send']);
        $this->assertFalse($container->hasDefinition(BeforeSendChainPass::CHAIN_ID));
    }

    public function testChainsWhenAppAlreadyConfiguredBeforeSend(): void
    {
        $container = $this->createContainerWithOptions([
            'before_send' => new Reference('app.custom_before_send'),
        ]);
        $container->setParameter('nowo_sentry.before_send_handler', [
            'enabled'                => true,
            'register_automatically' => true,
        ]);
        $container->setDefinition(BeforeSendChainPass::HANDLER_ID, new Definition());
        $container->setDefinition('app.custom_before_send', new Definition());

        (new BeforeSendChainPass())->process($container);

        $this->assertTrue($container->hasDefinition(BeforeSendChainPass::CHAIN_ID));
        $chain = $container->getDefinition(BeforeSendChainPass::CHAIN_ID);
        $this->assertSame(BeforeSendChain::class, $chain->getClass());
        $this->assertSame(BeforeSendChainPass::HANDLER_ID, (string) $chain->getArgument(0));
        $this->assertSame('app.custom_before_send', (string) $chain->getArgument(1));

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertSame(BeforeSendChainPass::CHAIN_ID, (string) $options['before_send']);
    }

    public function testSkipsWhenAutomaticRegistrationDisabled(): void
    {
        $container = $this->createContainerWithOptions([
            'before_send' => new Reference('app.custom_before_send'),
        ]);
        $container->setParameter('nowo_sentry.before_send_handler', [
            'enabled'                => true,
            'register_automatically' => false,
        ]);
        $container->setDefinition(BeforeSendChainPass::HANDLER_ID, new Definition());

        (new BeforeSendChainPass())->process($container);

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertSame('app.custom_before_send', (string) $options['before_send']);
        $this->assertFalse($container->hasDefinition(BeforeSendChainPass::CHAIN_ID));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createContainerWithOptions(array $options): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('sentry.client.options', (new Definition(Options::class))->setArgument(0, $options));

        return $container;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\SentryBundle\DependencyInjection\Compiler\BeforeSendTransactionChainPass;
use Nowo\SentryBundle\Sentry\BeforeSendChain;
use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BeforeSendTransactionChainPassTest extends TestCase
{
    public function testSetsHandlerWhenBeforeSendTransactionMissing(): void
    {
        $container = $this->createContainerWithOptions([]);
        $container->setParameter('nowo_sentry.before_send_transaction_handler', [
            'enabled'                => true,
            'register_automatically' => true,
        ]);
        $container->setDefinition(BeforeSendTransactionChainPass::HANDLER_ID, new Definition());

        (new BeforeSendTransactionChainPass())->process($container);

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertInstanceOf(Reference::class, $options['before_send_transaction']);
        $this->assertSame(BeforeSendTransactionChainPass::HANDLER_ID, (string) $options['before_send_transaction']);
        $this->assertFalse($container->hasDefinition(BeforeSendTransactionChainPass::CHAIN_ID));
    }

    public function testChainsWhenAppAlreadyConfiguredBeforeSendTransaction(): void
    {
        $container = $this->createContainerWithOptions([
            'before_send_transaction' => new Reference('app.custom_before_send_transaction'),
        ]);
        $container->setParameter('nowo_sentry.before_send_transaction_handler', [
            'enabled'                => true,
            'register_automatically' => true,
        ]);
        $container->setDefinition(BeforeSendTransactionChainPass::HANDLER_ID, new Definition());
        $container->setDefinition('app.custom_before_send_transaction', new Definition());

        (new BeforeSendTransactionChainPass())->process($container);

        $this->assertTrue($container->hasDefinition(BeforeSendTransactionChainPass::CHAIN_ID));
        $chain = $container->getDefinition(BeforeSendTransactionChainPass::CHAIN_ID);
        $this->assertSame(BeforeSendChain::class, $chain->getClass());
        $this->assertSame(BeforeSendTransactionChainPass::HANDLER_ID, (string) $chain->getArgument(0));
        $this->assertSame('app.custom_before_send_transaction', (string) $chain->getArgument(1));

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertSame(BeforeSendTransactionChainPass::CHAIN_ID, (string) $options['before_send_transaction']);
    }

    public function testSkipsWhenAutomaticRegistrationDisabled(): void
    {
        $container = $this->createContainerWithOptions([
            'before_send_transaction' => new Reference('app.custom_before_send_transaction'),
        ]);
        $container->setParameter('nowo_sentry.before_send_transaction_handler', [
            'enabled'                => true,
            'register_automatically' => false,
        ]);
        $container->setDefinition(BeforeSendTransactionChainPass::HANDLER_ID, new Definition());

        (new BeforeSendTransactionChainPass())->process($container);

        $options = $container->getDefinition('sentry.client.options')->getArgument(0);
        $this->assertSame('app.custom_before_send_transaction', (string) $options['before_send_transaction']);
        $this->assertFalse($container->hasDefinition(BeforeSendTransactionChainPass::CHAIN_ID));
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

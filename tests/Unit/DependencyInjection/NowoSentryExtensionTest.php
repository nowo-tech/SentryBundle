<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test case for NowoSentryExtension.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoSentryExtensionTest extends TestCase
{
    public function testGetAlias(): void
    {
        $extension = new NowoSentryExtension();
        $alias     = $extension->getAlias();

        $this->assertEquals(Configuration::ALIAS, $alias);
    }

    public function testLoad(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        $this->assertTrue($container->hasParameter('nowo_sentry.request_listener'));
        $this->assertTrue($container->hasParameter('nowo_sentry.ignore_access_denied_listener'));
        $this->assertTrue($container->hasParameter('nowo_sentry.sub_request_access_denied_listener'));
        $this->assertTrue($container->hasParameter('nowo_sentry.before_send_handler'));
        $this->assertTrue($container->hasParameter('nowo_sentry.uptime_bot_listener'));
    }

    public function testLoadWithListenersEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener'                   => ['enabled' => true, 'priority' => 0],
            'ignore_access_denied_listener'      => ['enabled' => true, 'priority' => 254],
            'sub_request_access_denied_listener' => ['enabled' => true, 'priority' => 256],
            'before_send_handler'                => ['enabled' => true, 'ignore_pure_access_denied' => true, 'register_automatically' => true],
            'uptime_bot_listener'                => ['enabled' => true, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition(SentryRequestListener::class));
        $this->assertTrue($container->hasDefinition(SubRequestAccessDeniedContextListener::class));
        $this->assertTrue($container->hasDefinition('nowo_sentry.before_send_handler'));
        $this->assertTrue($container->hasDefinition(SentryUptimeBotListener::class));
    }

    public function testLoadWithListenersDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener'                   => ['enabled' => false, 'priority' => 0],
            'ignore_access_denied_listener'      => ['enabled' => false, 'priority' => 254],
            'sub_request_access_denied_listener' => ['enabled' => false, 'priority' => 256],
            'before_send_handler'                => ['enabled' => false, 'ignore_pure_access_denied' => true, 'register_automatically' => true],
            'uptime_bot_listener'                => ['enabled' => false, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        $this->assertFalse($container->hasDefinition(SentryRequestListener::class));
        $this->assertFalse($container->hasDefinition(SubRequestAccessDeniedContextListener::class));
        $this->assertFalse($container->hasDefinition('nowo_sentry.before_send_handler'));
        $this->assertFalse($container->hasDefinition(SentryUptimeBotListener::class));
    }

    public function testIgnoreAccessDeniedDisabledMapsToBeforeSendHandler(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'ignore_access_denied_listener' => ['enabled' => false],
        ];

        $extension->load([$config], $container);

        $beforeSendConfig = $container->getParameter('nowo_sentry.before_send_handler');
        $this->assertIsArray($beforeSendConfig);
        $this->assertFalse($beforeSendConfig['ignore_pure_access_denied']);
    }

    public function testPrependDoesNothingWhenSentryExtensionMissing(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependDoesNothingWhenBeforeSendHandlerDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => ['enabled' => false],
        ]);

        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependDoesNothingWhenAutomaticRegistrationDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => ['register_automatically' => false],
        ]);

        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependRegistersBeforeSendHandlerWhenEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());

        $extension->prepend($container);

        $sentryConfigs = $container->getExtensionConfig('sentry');
        $this->assertNotEmpty($sentryConfigs);
        $this->assertSame('nowo_sentry.before_send_handler', $sentryConfigs[0]['options']['before_send']);
    }
}

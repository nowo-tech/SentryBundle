<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\EventListener\IgnoreAccessDeniedSentryListener;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test case for NowoSentryExtension.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class NowoSentryExtensionTest extends TestCase
{
    /**
     * Test that the extension has the correct alias.
     */
    public function testGetAlias(): void
    {
        $extension = new NowoSentryExtension();
        $alias = $extension->getAlias();

        $this->assertEquals(Configuration::ALIAS, $alias);
    }

    /**
     * Test that the extension loads without errors.
     */
    public function testLoad(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        // Verify that configuration parameters are set
        $this->assertTrue($container->hasParameter('nowo_sentry.request_listener'));
        $this->assertTrue($container->hasParameter('nowo_sentry.ignore_access_denied_listener'));
        $this->assertTrue($container->hasParameter('nowo_sentry.uptime_bot_listener'));
    }

    /**
     * Test that listeners are registered when enabled.
     */
    public function testLoadWithListenersEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener' => ['enabled' => true, 'priority' => 0],
            'ignore_access_denied_listener' => ['enabled' => true, 'priority' => 255],
            'uptime_bot_listener' => ['enabled' => true, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        // Verify listeners are registered
        $this->assertTrue($container->hasDefinition(SentryRequestListener::class));
        $this->assertTrue($container->hasDefinition(IgnoreAccessDeniedSentryListener::class));
        $this->assertTrue($container->hasDefinition(SentryUptimeBotListener::class));
    }

    /**
     * Test that listeners are removed when disabled.
     */
    public function testLoadWithListenersDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener' => ['enabled' => false, 'priority' => 0],
            'ignore_access_denied_listener' => ['enabled' => false, 'priority' => 255],
            'uptime_bot_listener' => ['enabled' => false, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        // Verify listeners are removed
        $this->assertFalse($container->hasDefinition(SentryRequestListener::class));
        $this->assertFalse($container->hasDefinition(IgnoreAccessDeniedSentryListener::class));
        $this->assertFalse($container->hasDefinition(SentryUptimeBotListener::class));
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionMiddleware;
use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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
        $this->assertTrue($container->hasParameter('nowo_sentry.dbal_exception_reporter'));
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

    public function testDbalExceptionReporterDisabledRemovesMiddleware(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            ['dbal_exception_reporter' => ['enabled' => false]],
        ], $container);

        $this->assertFalse($container->hasDefinition(SentryDbalExceptionMiddleware::class));
    }

    public function testDbalExceptionReporterRegistersMiddlewareTag(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            ['dbal_exception_reporter' => ['enabled' => true, 'priority' => 15, 'connections' => ['default']]],
        ], $container);

        $this->assertTrue($container->hasDefinition(SentryDbalExceptionMiddleware::class));
        $tags = $container->getDefinition(SentryDbalExceptionMiddleware::class)->getTags();
        $this->assertArrayHasKey('doctrine.middleware', $tags);
        $this->assertSame('default', $tags['doctrine.middleware'][0]['connection']);
        $this->assertSame(15, $tags['doctrine.middleware'][0]['priority']);
    }

    public function testPrependRegistersBeforeSendWhenAutomaticRegistrationEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new NowoSentryExtension());
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'sentry';
            }
        });
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => [
                'enabled'                => true,
                'register_automatically' => true,
            ],
        ]);

        $extension = new NowoSentryExtension();
        $extension->prepend($container);

        $sentryConfigs = $container->getExtensionConfig('sentry');
        $this->assertNotEmpty($sentryConfigs);
        $this->assertSame('nowo_sentry.before_send_handler', $sentryConfigs[0]['options']['before_send']);
    }

    public function testPrependSkipsWhenSentryExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new NowoSentryExtension());
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => [
                'enabled'                => true,
                'register_automatically' => true,
            ],
        ]);

        $extension = new NowoSentryExtension();
        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependSkipsWhenBeforeSendHandlerDisabled(): void
    {
        $container = $this->createContainerWithSentryExtension();
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => [
                'enabled'                => false,
                'register_automatically' => true,
            ],
        ]);

        $extension = new NowoSentryExtension();
        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependSkipsWhenAutomaticRegistrationDisabled(): void
    {
        $container = $this->createContainerWithSentryExtension();
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => [
                'enabled'                => true,
                'register_automatically' => false,
            ],
        ]);

        $extension = new NowoSentryExtension();
        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testDbalExceptionReporterRemovedWhenErrorReporterDisabled(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'error_reporter'          => ['enabled' => false],
                'dbal_exception_reporter' => ['enabled' => true],
            ],
        ], $container);

        $this->assertFalse($container->hasDefinition(SentryDbalExceptionMiddleware::class));
        $this->assertFalse($container->hasDefinition(ReportedSqlExceptionRegistry::class));
    }

    public function testDbalExceptionReporterRegistersGlobalMiddlewareWhenConnectionsEmpty(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            ['dbal_exception_reporter' => ['enabled' => true, 'connections' => [], 'priority' => 5]],
        ], $container);

        $tags = $container->getDefinition(SentryDbalExceptionMiddleware::class)->getTags();
        $this->assertArrayHasKey('doctrine.middleware', $tags);
        $this->assertSame(5, $tags['doctrine.middleware'][0]['priority']);
        $this->assertArrayNotHasKey('connection', $tags['doctrine.middleware'][0]);
    }

    public function testDbalExceptionReporterSkipsInvalidConnectionNames(): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            ['dbal_exception_reporter' => ['enabled' => true, 'connections' => ['default', '', 123, 'reporting']]],
        ], $container);

        $tags = $container->getDefinition(SentryDbalExceptionMiddleware::class)->getTags()['doctrine.middleware'];
        $this->assertCount(2, $tags);
        $this->assertSame('default', $tags[0]['connection']);
        $this->assertSame('reporting', $tags[1]['connection']);
    }

    private function createContainerWithSentryExtension(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new NowoSentryExtension());
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'sentry';
            }
        });

        return $container;
    }
}

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
        $this->assertTrue($container->hasParameter('nowo_sentry.before_send_transaction_handler'));
        $this->assertTrue($container->hasParameter('nowo_sentry.uptime_bot_listener'));
    }

    public function testLoadWithListenersEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener'                   => ['enabled' => true, 'priority' => 0],
            'ignore_access_denied_listener'      => ['enabled' => true],
            'sub_request_access_denied_listener' => ['enabled' => true, 'priority' => 256],
            'before_send_handler'                => ['enabled' => true, 'ignore_pure_access_denied' => true, 'register_automatically' => true],
            'before_send_transaction_handler'    => ['enabled' => true, 'register_automatically' => true],
            'uptime_bot_listener'                => ['enabled' => true, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        $this->assertTrue($container->hasDefinition(SentryRequestListener::class));
        $this->assertTrue($container->hasDefinition(SubRequestAccessDeniedContextListener::class));
        $this->assertTrue($container->hasDefinition('nowo_sentry.before_send_handler'));
        $this->assertTrue($container->hasDefinition('nowo_sentry.before_send_transaction_handler'));
        $this->assertTrue($container->hasDefinition(SentryUptimeBotListener::class));
    }

    public function testLoadWithListenersDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $config = [
            'request_listener'                   => ['enabled' => false, 'priority' => 0],
            'ignore_access_denied_listener'      => ['enabled' => false],
            'sub_request_access_denied_listener' => ['enabled' => false, 'priority' => 256],
            'before_send_handler'                => ['enabled' => false, 'ignore_pure_access_denied' => true, 'register_automatically' => true],
            'before_send_transaction_handler'    => ['enabled' => false, 'register_automatically' => true],
            'uptime_bot_listener'                => ['enabled' => false, 'priority' => 255, 'user_agents' => [], 'paths' => []],
        ];

        $extension->load([$config], $container);

        $this->assertFalse($container->hasDefinition(SentryRequestListener::class));
        $this->assertFalse($container->hasDefinition(SubRequestAccessDeniedContextListener::class));
        $this->assertFalse($container->hasDefinition('nowo_sentry.before_send_handler'));
        $this->assertFalse($container->hasDefinition('nowo_sentry.before_send_transaction_handler'));
        $this->assertFalse($container->hasDefinition(SentryUptimeBotListener::class));
    }

    public function testLoadRemovesErrorReporterAliasWhenDisabledButKeepsServiceForDbal(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'error_reporter'          => ['enabled' => false],
                'dbal_exception_reporter' => ['enabled' => true],
            ],
        ], $container);

        $this->assertFalse($container->hasAlias('nowo_sentry.error_reporter'));

        if (interface_exists(\Doctrine\DBAL\Driver\Middleware::class)
            && interface_exists(\Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface::class)) {
            $this->assertTrue($container->hasDefinition(\Nowo\SentryBundle\Service\SentryErrorReporter::class));
            $this->assertFalse($container->getDefinition(\Nowo\SentryBundle\Service\SentryErrorReporter::class)->isPublic());
            $this->assertTrue($container->hasDefinition(\Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter::class));
        } else {
            $this->assertFalse($container->hasDefinition(\Nowo\SentryBundle\Service\SentryErrorReporter::class));
        }
    }

    public function testLoadRemovesErrorReporterWhenDisabledAndDbalDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'error_reporter'          => ['enabled' => false],
                'dbal_exception_reporter' => ['enabled' => false],
            ],
        ], $container);

        $this->assertFalse($container->hasDefinition(\Nowo\SentryBundle\Service\SentryErrorReporter::class));
        $this->assertFalse($container->hasAlias('nowo_sentry.error_reporter'));
    }

    public function testLoadRegistersErrorReporterAliasWhenEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        $this->assertTrue($container->hasDefinition(\Nowo\SentryBundle\Service\SentryErrorReporter::class));
        $this->assertTrue($container->hasAlias('nowo_sentry.error_reporter'));
    }

    public function testLoadDoesNotSetDuplicatePriorityParameters(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        $this->assertFalse($container->hasParameter('nowo_sentry.request_listener.priority'));
        $this->assertFalse($container->hasParameter('nowo_sentry.uptime_bot_listener.priority'));
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

    public function testPrependDoesNothingWhenBeforeSendHandlersDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler'             => ['enabled' => false],
            'before_send_transaction_handler' => ['enabled' => false],
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
            'before_send_handler'             => ['register_automatically' => false],
            'before_send_transaction_handler' => ['register_automatically' => false],
        ]);

        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('sentry'));
    }

    public function testPrependRegistersBeforeSendHandlersWhenEnabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());

        $extension->prepend($container);

        $sentryConfigs = $container->getExtensionConfig('sentry');
        $this->assertNotEmpty($sentryConfigs);
        $this->assertSame('nowo_sentry.before_send_handler', $sentryConfigs[0]['options']['before_send']);
        $this->assertSame(
            'nowo_sentry.before_send_transaction_handler',
            $sentryConfigs[0]['options']['before_send_transaction'],
        );
    }

    public function testPrependRegistersOnlyTransactionHandlerWhenBeforeSendDisabled(): void
    {
        $extension = new NowoSentryExtension();
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension(new \Sentry\SentryBundle\DependencyInjection\SentryExtension());
        $container->loadFromExtension('nowo_sentry', [
            'before_send_handler' => ['enabled' => false],
        ]);

        $extension->prepend($container);

        $sentryConfigs = $container->getExtensionConfig('sentry');
        $this->assertNotEmpty($sentryConfigs);
        $this->assertArrayNotHasKey('before_send', $sentryConfigs[0]['options']);
        $this->assertSame(
            'nowo_sentry.before_send_transaction_handler',
            $sentryConfigs[0]['options']['before_send_transaction'],
        );
    }
}

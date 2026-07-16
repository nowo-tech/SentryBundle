<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\DependencyInjection;

use Nowo\SentryBundle\Doctrine\DBAL\Middleware\SentryDbalExceptionMiddleware;
use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use Nowo\SentryBundle\Doctrine\DBAL\SqlExceptionReporter;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use Nowo\SentryBundle\EventListener\SubRequestAccessDeniedContextListener;
use Nowo\SentryBundle\Sentry\EventPayloadTrimmer;
use Nowo\SentryBundle\Service\SentryErrorReporter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use function is_string;

/**
 * Extension for loading the bundle configuration.
 *
 * This extension loads the services configuration and processes the bundle configuration.
 * It registers all services defined in the services.yaml file.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoSentryExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sentry')) {
            return;
        }

        $configs       = $container->getExtensionConfig($this->getAlias());
        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        $sentryOptions = [];

        if (($config['before_send_handler']['enabled'] ?? true)
            && ($config['before_send_handler']['register_automatically'] ?? true)
        ) {
            $sentryOptions['before_send'] = 'nowo_sentry.before_send_handler';
        }

        if (($config['before_send_transaction_handler']['enabled'] ?? true)
            && ($config['before_send_transaction_handler']['register_automatically'] ?? true)
        ) {
            $sentryOptions['before_send_transaction'] = 'nowo_sentry.before_send_transaction_handler';
        }

        if ($sentryOptions === []) {
            return;
        }

        $container->prependExtensionConfig('sentry', [
            'options' => $sentryOptions,
        ]);
    }

    /**
     * Loads the bundle configuration and services.
     *
     * @param array<int, array<string, mixed>> $configs The configuration arrays (from config files)
     * @param ContainerBuilder $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        $beforeSendHandler = $config['before_send_handler'];
        if (!($config['ignore_access_denied_listener']['enabled'] ?? true)) {
            $beforeSendHandler['ignore_pure_access_denied'] = false;
        }
        $beforeSendHandler['deduplicate_sql_exceptions'] = ($config['dbal_exception_reporter']['enabled'] ?? true)
            && ($config['dbal_exception_reporter']['deduplicate'] ?? true);

        // Set configuration parameters
        $container->setParameter(Configuration::ALIAS . '.request_listener', $config['request_listener']);
        $container->setParameter(Configuration::ALIAS . '.ignore_access_denied_listener', $config['ignore_access_denied_listener']);
        $container->setParameter(Configuration::ALIAS . '.sub_request_access_denied_listener', $config['sub_request_access_denied_listener']);
        $container->setParameter(Configuration::ALIAS . '.before_send_handler', $beforeSendHandler);
        $container->setParameter(Configuration::ALIAS . '.before_send_transaction_handler', $config['before_send_transaction_handler']);
        $container->setParameter(Configuration::ALIAS . '.uptime_bot_listener', $config['uptime_bot_listener']);
        $container->setParameter(Configuration::ALIAS . '.error_reporter', $config['error_reporter']);
        $container->setParameter(Configuration::ALIAS . '.dbal_exception_reporter', $config['dbal_exception_reporter']);

        // Load services configuration
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (interface_exists(\Doctrine\DBAL\Driver\Middleware::class)
            && interface_exists(\Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface::class)) {
            $loader->load('doctrine_dbal.yaml');
        }

        // Conditionally register listeners based on configuration
        $this->registerListeners($container, $config);
        $this->registerDbalExceptionReporter($container, $config);
        $this->registerErrorReporter($container, $config);
    }

    /**
     * Registers event listeners conditionally based on configuration.
     *
     * @param ContainerBuilder $container The container builder
     * @param array<string, mixed> $config The processed configuration
     */
    private function registerListeners(ContainerBuilder $container, array $config): void
    {
        // Register request listener if enabled
        if ($config['request_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(SentryRequestListener::class)) {
                $definition = $container->getDefinition(SentryRequestListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event'    => 'kernel.request',
                    'method'   => 'onKernelRequest',
                    'priority' => $config['request_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(SentryRequestListener::class);
        }

        // Register sub-request access denied context listener if enabled
        if ($config['sub_request_access_denied_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(SubRequestAccessDeniedContextListener::class)) {
                $definition = $container->getDefinition(SubRequestAccessDeniedContextListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event'    => 'kernel.exception',
                    'method'   => '__invoke',
                    'priority' => $config['sub_request_access_denied_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(SubRequestAccessDeniedContextListener::class);
        }

        if (!($config['before_send_handler']['enabled'] ?? true)) {
            $container->removeDefinition('nowo_sentry.before_send_handler');
        }

        if (!($config['before_send_transaction_handler']['enabled'] ?? true)) {
            $container->removeDefinition('nowo_sentry.before_send_transaction_handler');
            $container->removeDefinition(EventPayloadTrimmer::class);
        }

        // Register uptime bot listener if enabled
        if ($config['uptime_bot_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(SentryUptimeBotListener::class)) {
                $definition = $container->getDefinition(SentryUptimeBotListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event'    => 'kernel.request',
                    'method'   => 'onKernelRequest',
                    'priority' => $config['uptime_bot_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(SentryUptimeBotListener::class);
        }
    }

    /**
     * Registers or removes SentryErrorReporter independently from DBAL reporting.
     *
     * @param ContainerBuilder $container The container builder
     * @param array<string, mixed> $config The processed configuration
     */
    private function registerErrorReporter(ContainerBuilder $container, array $config): void
    {
        if ($config['error_reporter']['enabled'] ?? true) {
            return;
        }

        if ($container->hasAlias('nowo_sentry.error_reporter')) {
            $container->removeAlias('nowo_sentry.error_reporter');
        }

        // DBAL SqlExceptionReporter depends on SentryErrorReporter; keep it private if still needed
        if ($container->hasDefinition(SqlExceptionReporter::class)) {
            if ($container->hasDefinition(SentryErrorReporter::class)) {
                $container->getDefinition(SentryErrorReporter::class)->setPublic(false);
            }

            return;
        }

        $container->removeDefinition(SentryErrorReporter::class);
    }

    /**
     * Registers the DBAL SQL exception middleware when enabled and Doctrine is available.
     *
     * @param ContainerBuilder $container The container builder
     * @param array<string, mixed> $config The processed configuration
     */
    private function registerDbalExceptionReporter(ContainerBuilder $container, array $config): void
    {
        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)
            || !interface_exists(\Doctrine\Bundle\DoctrineBundle\Middleware\ConnectionNameAwareInterface::class)) {
            return;
        }

        if (!($config['dbal_exception_reporter']['enabled'] ?? true)) {
            $container->removeDefinition(SentryDbalExceptionMiddleware::class);
            $container->removeDefinition(ReportedSqlExceptionRegistry::class);
            $container->removeDefinition(SqlExceptionReporter::class);

            return;
        }

        if (!$container->hasDefinition(SentryDbalExceptionMiddleware::class)) {
            return;
        }

        $definition = $container->getDefinition(SentryDbalExceptionMiddleware::class);
        $definition->clearTags();

        $connections = $config['dbal_exception_reporter']['connections'] ?? [];
        $priority    = $config['dbal_exception_reporter']['priority'] ?? 20;

        if ($connections === []) {
            $definition->addTag('doctrine.middleware', ['priority' => $priority]);

            return;
        }

        foreach ($connections as $connection) {
            if (!is_string($connection) || $connection === '') {
                continue;
            }

            $definition->addTag('doctrine.middleware', [
                'connection' => $connection,
                'priority'   => $priority,
            ]);
        }
    }

    /**
     * Returns the extension alias.
     *
     * @return string The extension alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}

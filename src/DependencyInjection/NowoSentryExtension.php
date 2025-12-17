<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\DependencyInjection;

use Nowo\SentryBundle\EventListener\IgnoreAccessDeniedSentryListener;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for loading the bundle configuration.
 *
 * This extension loads the services configuration and processes the bundle configuration.
 * It registers all services defined in the services.yaml file.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class NowoSentryExtension extends Extension
{
    /**
     * Loads the bundle configuration and services.
     *
     * @param array<string, mixed> $configs   The configuration array
     * @param ContainerBuilder     $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration parameters
        $container->setParameter(Configuration::ALIAS . '.request_listener', $config['request_listener']);
        $container->setParameter(Configuration::ALIAS . '.ignore_access_denied_listener', $config['ignore_access_denied_listener']);
        $container->setParameter(Configuration::ALIAS . '.uptime_bot_listener', $config['uptime_bot_listener']);
        $container->setParameter(Configuration::ALIAS . '.error_reporter', $config['error_reporter']);

        // Set individual priority parameters for easier access in services.yaml
        $container->setParameter(Configuration::ALIAS . '.request_listener.priority', $config['request_listener']['priority']);
        $container->setParameter(Configuration::ALIAS . '.ignore_access_denied_listener.priority', $config['ignore_access_denied_listener']['priority']);
        $container->setParameter(Configuration::ALIAS . '.uptime_bot_listener.priority', $config['uptime_bot_listener']['priority']);

        // Load services configuration
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Conditionally register listeners based on configuration
        $this->registerListeners($container, $config);
    }

    /**
     * Registers event listeners conditionally based on configuration.
     *
     * @param ContainerBuilder     $container The container builder
     * @param array<string, mixed> $config    The processed configuration
     */
    private function registerListeners(ContainerBuilder $container, array $config): void
    {
        // Register request listener if enabled
        if ($config['request_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(SentryRequestListener::class)) {
                $definition = $container->getDefinition(SentryRequestListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event' => 'kernel.request',
                    'method' => 'onKernelRequest',
                    'priority' => $config['request_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(SentryRequestListener::class);
        }

        // Register ignore access denied listener if enabled
        if ($config['ignore_access_denied_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(IgnoreAccessDeniedSentryListener::class)) {
                $definition = $container->getDefinition(IgnoreAccessDeniedSentryListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event' => 'kernel.exception',
                    'method' => '__invoke',
                    'priority' => $config['ignore_access_denied_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(IgnoreAccessDeniedSentryListener::class);
        }

        // Register uptime bot listener if enabled
        if ($config['uptime_bot_listener']['enabled'] ?? true) {
            if ($container->hasDefinition(SentryUptimeBotListener::class)) {
                $definition = $container->getDefinition(SentryUptimeBotListener::class);
                $definition->clearTags();
                $definition->addTag('kernel.event_listener', [
                    'event' => 'kernel.request',
                    'method' => 'onKernelRequest',
                    'priority' => $config['uptime_bot_listener']['priority'],
                ]);
            }
        } else {
            $container->removeDefinition(SentryUptimeBotListener::class);
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

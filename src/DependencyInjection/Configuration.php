<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\DependencyInjection;

use RuntimeException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Yaml\Yaml;

use function dirname;

/**
 * Configuration class for the bundle.
 *
 * Defines the configuration structure for the SentryBundle.
 * Allows configuring all event listeners and their behavior.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class Configuration implements ConfigurationInterface
{
    /**
     * The extension alias.
     */
    public const ALIAS = 'nowo_sentry';

    /**
     * Builds the configuration tree.
     *
     * @return TreeBuilder The configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('request_listener')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->booleanNode('set_domain_tag')
                            ->defaultTrue()
                            ->info('Whether to set the domain tag in Sentry scope')
                        ->end()
                        ->booleanNode('set_environment_tag')
                            ->defaultTrue()
                            ->info('Whether to set the environment tag in Sentry scope')
                        ->end()
                        ->booleanNode('set_user_info')
                            ->defaultTrue()
                            ->info('Whether to set user information in Sentry scope')
                        ->end()
                        ->booleanNode('set_session_id')
                            ->defaultFalse()
                            ->info('Whether to set session ID in Sentry scope extra data')
                        ->end()
                        ->integerNode('priority')
                            ->defaultValue(0)
                            ->info('Event listener priority')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('ignore_access_denied_listener')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('priority')
                            ->defaultValue(254)
                            ->info('Deprecated: listener removed; kept for BC. Use before_send_handler.ignore_pure_access_denied.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sub_request_access_denied_listener')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('priority')
                            ->defaultValue(256)
                            ->info('Event listener priority for enriching Sentry when a sub-request 403 breaks the parent page')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('before_send_handler')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->booleanNode('ignore_pure_access_denied')
                            ->defaultTrue()
                            ->info('Drop pure AccessDeniedException/AccessDeniedHttpException; keep parent-page failures that wrap a sub-request 403')
                        ->end()
                        ->booleanNode('register_automatically')
                            ->defaultTrue()
                            ->info('Prepend sentry.options.before_send with nowo_sentry.before_send_handler when not configured by the app')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('uptime_bot_listener')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('user_agents')
                            ->defaultValue(['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'])
                            ->prototype('scalar')->end()
                            ->info('List of user agent prefixes to detect as uptime bots')
                        ->end()
                        ->arrayNode('paths')
                            ->defaultValue(['/dashboard', '/', '/login'])
                            ->prototype('scalar')->end()
                            ->info('List of paths that should return OK for uptime bots')
                        ->end()
                        ->integerNode('priority')
                            ->defaultValue(255)
                            ->info('Event listener priority (higher = earlier execution)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('error_reporter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Whether the error reporter service is enabled')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Generates a default configuration YAML file at the given path.
     *
     * @param string $configPath Absolute path to the configuration file to generate
     *
     * @throws RuntimeException If the symfony/yaml component is not installed
     */
    public function generateConfigFile(string $configPath): void
    {
        if (!$this->hasYamlComponent()) {
            throw new RuntimeException('Missing symfony/yaml component. Install it with: composer require symfony/yaml');
        }

        $config = [
            self::ALIAS => [
                'request_listener' => [
                    'enabled'             => true,
                    'set_domain_tag'      => true,
                    'set_environment_tag' => true,
                    'set_user_info'       => true,
                    'set_session_id'      => false,
                    'priority'            => 0,
                ],
                'ignore_access_denied_listener' => [
                    'enabled'  => true,
                    'priority' => 254,
                ],
                'sub_request_access_denied_listener' => [
                    'enabled'  => true,
                    'priority' => 256,
                ],
                'before_send_handler' => [
                    'enabled'                   => true,
                    'ignore_pure_access_denied' => true,
                    'register_automatically'    => true,
                ],
                'uptime_bot_listener' => [
                    'enabled'     => true,
                    'user_agents' => [
                        'SentryUptimeBot/1.0',
                        'Uptime-Kuma',
                        'kube-probe',
                    ],
                    'paths' => [
                        '/dashboard',
                        '/',
                        '/login',
                    ],
                    'priority' => 255,
                ],
                'error_reporter' => [
                    'enabled' => true,
                ],
            ],
        ];

        $yaml = Yaml::dump($config, 4, 2);

        $dir = dirname($configPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        file_put_contents($configPath, $yaml);
    }

    /**
     * Whether the Symfony YAML component is available (used for testing the exception path).
     */
    protected function hasYamlComponent(): bool
    {
        return class_exists(Yaml::class);
    }
}

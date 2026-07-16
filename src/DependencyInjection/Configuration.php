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
                    ->setDeprecated(
                        'nowo-tech/sentry-bundle',
                        '1.7',
                        'The "%node%" option is deprecated; use "before_send_handler.ignore_pure_access_denied" instead.',
                    )
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
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
                            ->info('Register as sentry.options.before_send; chains with an existing app before_send when present')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('uptime_bot_listener')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('user_agents')
                            ->defaultValue(['SentryUptimeBot/1.0'])
                            ->prototype('scalar')->end()
                            ->info('List of user agent prefixes to detect as uptime bots')
                        ->end()
                        ->arrayNode('paths')
                            ->defaultValue(['/health'])
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
                    ->canBeDisabled()
                    ->info('Registers SentryErrorReporter and alias nowo_sentry.error_reporter')
                ->end()
                ->arrayNode('dbal_exception_reporter')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('connections')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                            ->info('Doctrine connection names to monitor; empty means all connections')
                        ->end()
                        ->arrayNode('sql_states')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                            ->info('SQLSTATE codes to report (e.g. 42S22 for column not found); empty means all SQL exceptions')
                        ->end()
                        ->integerNode('priority')
                            ->defaultValue(20)
                            ->info('doctrine.middleware priority')
                        ->end()
                        ->integerNode('max_sql_length')
                            ->defaultValue(2000)
                            ->info('Maximum SQL query length stored in Sentry extra data')
                        ->end()
                        ->booleanNode('deduplicate')
                            ->defaultTrue()
                            ->info('Drop duplicate events already reported by the DBAL middleware')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Generates a default configuration YAML file at the given path.
     *
     * @internal used by tests and optional tooling; not part of the public consumer API
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
                    'enabled' => true,
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
                    ],
                    'paths' => [
                        '/health',
                    ],
                    'priority' => 255,
                ],
                'error_reporter' => [
                    'enabled' => true,
                ],
                'dbal_exception_reporter' => [
                    'enabled'        => true,
                    'connections'    => [],
                    'sql_states'     => [],
                    'priority'       => 20,
                    'max_sql_length' => 2000,
                    'deduplicate'    => true,
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

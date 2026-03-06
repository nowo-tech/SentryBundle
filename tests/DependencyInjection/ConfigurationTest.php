<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Test case for Configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class ConfigurationTest extends TestCase
{
    /**
     * Test that the configuration has the correct alias.
     */
    public function testAlias(): void
    {
        $this->assertEquals('nowo_sentry', Configuration::ALIAS);
    }

    /**
     * Test that getConfigTreeBuilder returns a TreeBuilder.
     */
    public function testGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder   = $configuration->getConfigTreeBuilder();

        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }

    /**
     * Test that generateConfigFile creates a YAML file with the expected structure.
     */
    public function testGenerateConfigFile(): void
    {
        $configDir  = sys_get_temp_dir() . '/sentry-bundle-test-' . uniqid('', true);
        $configPath = $configDir . '/nowo_sentry.yaml';

        $this->assertDirectoryDoesNotExist($configDir);

        $configuration = new Configuration();
        $configuration->generateConfigFile($configPath);

        $this->assertFileExists($configPath);
        $content = file_get_contents($configPath);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('nowo_sentry:', $content);
        $this->assertStringContainsString('request_listener:', $content);
        $this->assertStringContainsString('ignore_access_denied_listener:', $content);
        $this->assertStringContainsString('uptime_bot_listener:', $content);
        $this->assertStringContainsString('error_reporter:', $content);

        unlink($configPath);
        rmdir($configDir);
    }

    /**
     * Test that generateConfigFile throws when YAML component is not available.
     */
    public function testGenerateConfigFileThrowsWhenYamlMissing(): void
    {
        $configPath = sys_get_temp_dir() . '/sentry-bundle-no-yaml-' . uniqid('', true) . '.yaml';
        $config     = new class extends Configuration {
            protected function hasYamlComponent(): bool
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing symfony/yaml component');

        $config->generateConfigFile($configPath);
    }
}

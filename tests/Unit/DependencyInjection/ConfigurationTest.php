<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

use function array_filter;
use function file_get_contents;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const E_USER_DEPRECATED;

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
     * Empty config must not trigger the ignore_access_denied_listener deprecation.
     */
    public function testDefaultConfigDoesNotTriggerIgnoreAccessDeniedDeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_USER_DEPRECATED) {
                $deprecations[] = $message;

                return true;
            }

            return false;
        });

        try {
            $processor = new Processor();
            $config    = $processor->processConfiguration(new Configuration(), [[]]);
        } finally {
            restore_error_handler();
        }

        $this->assertArrayNotHasKey('ignore_access_denied_listener', $config);
        $this->assertTrue($config['before_send_handler']['ignore_pure_access_denied']);
        $this->assertSame([], array_filter(
            $deprecations,
            static fn (string $message): bool => str_contains($message, 'ignore_access_denied_listener'),
        ));
    }

    /**
     * Explicit use of the legacy option still triggers the deprecation.
     */
    public function testExplicitIgnoreAccessDeniedListenerTriggersDeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_USER_DEPRECATED) {
                $deprecations[] = $message;

                return true;
            }

            return false;
        });

        try {
            $processor = new Processor();
            $processor->processConfiguration(new Configuration(), [[
                'ignore_access_denied_listener' => ['enabled' => false],
            ]]);
        } finally {
            restore_error_handler();
        }

        $this->assertNotEmpty(array_filter(
            $deprecations,
            static fn (string $message): bool => str_contains($message, 'ignore_access_denied_listener'),
        ));
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
        $this->assertStringNotContainsString('ignore_access_denied_listener:', $content);
        $this->assertStringContainsString('sub_request_access_denied_listener:', $content);
        $this->assertStringContainsString('before_send_handler:', $content);
        $this->assertStringContainsString('ignore_pure_access_denied:', $content);
        $this->assertStringContainsString('before_send_transaction_handler:', $content);
        $this->assertStringContainsString('uptime_bot_listener:', $content);
        $this->assertStringContainsString('error_reporter:', $content);
        $this->assertStringContainsString('dbal_exception_reporter:', $content);

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

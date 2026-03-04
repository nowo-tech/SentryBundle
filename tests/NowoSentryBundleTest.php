<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests;

use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\NowoSentryBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Test case for NowoSentryBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class NowoSentryBundleTest extends TestCase
{
    /**
     * Test that the bundle returns the correct extension.
     */
    public function testGetContainerExtension(): void
    {
        $bundle    = new NowoSentryBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ExtensionInterface::class, $extension);
        $this->assertInstanceOf(NowoSentryExtension::class, $extension);
    }

    /**
     * Test that the bundle returns the same extension instance on multiple calls.
     */
    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $bundle     = new NowoSentryBundle();
        $extension1 = $bundle->getContainerExtension();
        $extension2 = $bundle->getContainerExtension();

        $this->assertSame($extension1, $extension2);
    }

    /**
     * Test that the bundle extends SentryBundle.
     */
    public function testGetParent(): void
    {
        $bundle = new NowoSentryBundle();
        $parent = $bundle->getParent();

        $this->assertEquals('SentryBundle', $parent);
    }

    /**
     * Test that boot() does nothing when kernel.project_dir is not set.
     */
    public function testBootWhenNoProjectDir(): void
    {
        $container = new ContainerBuilder();
        $bundle    = new NowoSentryBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $this->assertFalse($container->hasParameter('kernel.project_dir'));
    }

    /**
     * Test that boot() creates config file when config dir exists and file does not.
     */
    public function testBootCreatesConfigFileWhenMissing(): void
    {
        $projectDir = sys_get_temp_dir() . '/sentry-bundle-boot-' . uniqid('', true);
        $configDir  = $projectDir . '/config/packages';
        mkdir($configDir, 0o775, true);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);

        $bundle = new NowoSentryBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $configPath = $configDir . '/nowo_sentry.yaml';
        $this->assertFileExists($configPath);
        $this->assertStringContainsString('nowo_sentry:', file_get_contents($configPath));

        unlink($configPath);
        rmdir($configDir);
        rmdir($projectDir . '/config');
        rmdir($projectDir);
    }

    /**
     * Test that boot() creates config when config dir has other files but none define nowo_sentry.
     */
    public function testBootCreatesConfigWhenOtherConfigFilesExist(): void
    {
        $projectDir = sys_get_temp_dir() . '/sentry-bundle-boot-other-' . uniqid('', true);
        $configDir  = $projectDir . '/config/packages';
        mkdir($configDir, 0o775, true);
        file_put_contents($configDir . '/framework.yaml', "framework:\n  secret: test\n");

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);

        $bundle = new NowoSentryBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $configPath = $configDir . '/nowo_sentry.yaml';
        $this->assertFileExists($configPath);
        $this->assertStringContainsString('nowo_sentry:', file_get_contents($configPath));

        unlink($configPath);
        unlink($configDir . '/framework.yaml');
        rmdir($configDir);
        rmdir($projectDir . '/config');
        rmdir($projectDir);
    }

    /**
     * Test that boot() does not overwrite when config is already defined in a file.
     */
    public function testBootSkipsWhenConfigurationDefined(): void
    {
        $projectDir = sys_get_temp_dir() . '/sentry-bundle-boot-skip-' . uniqid('', true);
        $configDir  = $projectDir . '/config/packages';
        mkdir($configDir, 0o775, true);
        $existingFile = $configDir . '/some.yaml';
        file_put_contents($existingFile, "nowo_sentry:\n  request_listener:\n    enabled: true\n");

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);

        $bundle = new NowoSentryBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $configPath = $configDir . '/nowo_sentry.yaml';
        $this->assertFileDoesNotExist($configPath);

        unlink($existingFile);
        rmdir($configDir);
        rmdir($projectDir . '/config');
        rmdir($projectDir);
    }

    /**
     * Test that boot() skips when config is defined in a .yml file (covers glob *.yml branch).
     */
    public function testBootSkipsWhenConfigurationDefinedInYmlFile(): void
    {
        $projectDir = sys_get_temp_dir() . '/sentry-bundle-boot-yml-' . uniqid('', true);
        $configDir  = $projectDir . '/config/packages';
        mkdir($configDir, 0o775, true);
        $existingFile = $configDir . '/nowo_sentry.yml';
        file_put_contents($existingFile, "nowo_sentry:\n  request_listener:\n    enabled: true\n");

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);

        $bundle = new NowoSentryBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $configPath = $configDir . '/nowo_sentry.yaml';
        $this->assertFileDoesNotExist($configPath);

        unlink($existingFile);
        rmdir($configDir);
        rmdir($projectDir . '/config');
        rmdir($projectDir);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function is_string;
use function sprintf;

/**
 * Symfony bundle for enhanced Sentry integration.
 *
 * This bundle extends the official Sentry Symfony bundle with additional
 * event listeners and configuration options to improve error reporting
 * and monitoring capabilities.
 *
 * Features:
 * - Enhanced request context with user and session information
 * - Automatic filtering of access denied exceptions
 * - Uptime bot detection and handling
 * - Compatible with existing Sentry configuration
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoSentryBundle extends Bundle
{
    /**
     * Returns the bundle name that this bundle overrides.
     *
     * This method allows the bundle to extend the SentryBundle,
     * inheriting all its configuration and services.
     *
     * @return string|null The bundle name it overrides or null if no parent
     */
    public function getParent(): ?string
    {
        return 'SentryBundle';
    }

    /**
     * Overridden to allow for the custom extension alias.
     *
     * Creates and returns the container extension instance if not already created.
     * The extension is cached after the first call to ensure the same instance is returned
     * on subsequent calls.
     *
     * @return ExtensionInterface|null The container extension instance, or null if not available
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new NowoSentryExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }

    /**
     * Generates the configuration file if it doesn't exist.
     */
    public function boot(): void
    {
        parent::boot();

        if (!$this->container instanceof \Symfony\Component\DependencyInjection\ContainerInterface || !$this->container->hasParameter('kernel.project_dir')) {
            return;
        }

        $projectDir = $this->container->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            return;
        }

        $aliasBundle = Configuration::ALIAS;
        $configPath  = $projectDir . sprintf('/config/packages/%s.yaml', $aliasBundle);
        $configDir   = $projectDir . '/config/packages';

        // Check if the configuration already exists in any file
        if ($this->isConfigurationDefined($configDir)) {
            return;
        }

        // If it doesn't exist, create the configuration file
        if (!file_exists($configPath)) {
            $configuration = new Configuration();
            $configuration->generateConfigFile($configPath);
        }
    }

    /**
     * Checks if the configuration is already defined in any config file.
     */
    private function isConfigurationDefined(string $configDir): bool
    {
        if (!is_dir($configDir)) {
            return false;
        }

        $files = array_merge(
            glob($configDir . '/*.yaml') ?: [],
            glob($configDir . '/*.yml') ?: [],
        );

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content && str_contains($content, Configuration::ALIAS . ':')) {
                return true;
            }
        }

        return false;
    }
}

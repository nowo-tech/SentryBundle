<?php

declare(strict_types=1);

namespace Nowo\SentryBundle;

use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for enhanced Sentry integration.
 *
 * This bundle complements the official Sentry Symfony bundle with additional
 * event listeners and configuration options to improve error reporting
 * and monitoring capabilities.
 *
 * Features:
 * - Enhanced request context with user and session information
 * - Configurable filtering of main-request access denied exceptions
 * - Sub-request access denied reporting with Sentry context enrichment
 * - Uptime bot detection and handling
 * - Doctrine DBAL SQL exception reporting (including caught errors)
 * - Compatible with existing Sentry configuration
 *
 * Register both {@see \Sentry\SentryBundle\SentryBundle} and this bundle.
 * Symfony Flex recipes typically register both; otherwise add them in bundles.php.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoSentryBundle extends Bundle
{
    public function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DependencyInjection\Compiler\BeforeSendChainPass());
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
}

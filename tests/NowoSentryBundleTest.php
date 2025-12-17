<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests;

use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\NowoSentryBundle;
use PHPUnit\Framework\TestCase;
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
        $bundle = new NowoSentryBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ExtensionInterface::class, $extension);
        $this->assertInstanceOf(NowoSentryExtension::class, $extension);
    }

    /**
     * Test that the bundle returns the same extension instance on multiple calls.
     */
    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $bundle = new NowoSentryBundle();
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
}

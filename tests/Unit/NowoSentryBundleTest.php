<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit;

use Nowo\SentryBundle\DependencyInjection\NowoSentryExtension;
use Nowo\SentryBundle\NowoSentryBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Test case for NowoSentryBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
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
     * Bundle inheritance via getParent() is not used on Symfony 6+.
     */
    public function testDoesNotDeclareParentBundle(): void
    {
        $this->assertFalse(method_exists(NowoSentryBundle::class, 'getParent'));
    }

    public function testBuildRegistersBeforeSendChainPass(): void
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $bundle    = new NowoSentryBundle();
        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        $found  = false;
        foreach ($passes as $pass) {
            if ($pass instanceof \Nowo\SentryBundle\DependencyInjection\Compiler\BeforeSendChainPass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }
}

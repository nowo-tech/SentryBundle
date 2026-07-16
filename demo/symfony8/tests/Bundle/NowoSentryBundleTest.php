<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use Nowo\SentryBundle\NowoSentryBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Tests for Sentry Bundle integration.
 *
 * Verifies that the bundle is correctly registered.
 *
 * @covers \Nowo\SentryBundle\NowoSentryBundle
 */
final class NowoSentryBundleTest extends TestCase
{
    /**
     * Tests that the bundle extends Symfony Bundle class.
     */
    public function testBundleExtendsSymfonyBundle(): void
    {
        $bundle = new NowoSentryBundle();

        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    /**
     * Tests that the bundle has a container extension.
     */
    public function testBundleHasContainerExtension(): void
    {
        $bundle    = new NowoSentryBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertNotNull($extension);
        $this->assertSame('nowo_sentry', $extension->getAlias());
    }

    /**
     * Bundle inheritance via getParent() is not used on Symfony 6+.
     */
    public function testDoesNotDeclareParentBundle(): void
    {
        $this->assertFalse(method_exists(NowoSentryBundle::class, 'getParent'));
    }
}

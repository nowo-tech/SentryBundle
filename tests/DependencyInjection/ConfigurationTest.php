<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\DependencyInjection;

use Nowo\SentryBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Test case for Configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
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
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }
}

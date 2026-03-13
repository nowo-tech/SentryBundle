<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Integration;

use Nowo\SentryBundle\EventListener\IgnoreAccessDeniedSentryListener;
use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\EventListener\SentryUptimeBotListener;
use Nowo\SentryBundle\Service\SentryErrorReporter;
use Nowo\SentryBundle\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests: kernel boots with the bundle and services are available.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testKernelBoots(): void
    {
        self::bootKernel();
        $this->assertTrue(self::getContainer()->has('kernel'));
    }

    public function testBundleServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue(
            $container->has(SentryRequestListener::class),
            'SentryRequestListener should be registered',
        );
        $this->assertTrue(
            $container->has(IgnoreAccessDeniedSentryListener::class),
            'IgnoreAccessDeniedSentryListener should be registered',
        );
        $this->assertTrue(
            $container->has(SentryUptimeBotListener::class),
            'SentryUptimeBotListener should be registered',
        );
        $this->assertTrue(
            $container->has(SentryErrorReporter::class),
            'SentryErrorReporter (public) should be registered',
        );
    }

    public function testSentryErrorReporterIsPublic(): void
    {
        self::bootKernel();
        $reporter = self::getContainer()->get(SentryErrorReporter::class);
        $this->assertInstanceOf(SentryErrorReporter::class, $reporter);
    }
}

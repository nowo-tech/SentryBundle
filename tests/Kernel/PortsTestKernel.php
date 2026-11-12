<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Kernel;

use Closure;
use Nowo\SentryBundle\NowoSentryBundle;
use Sentry\SentryBundle\SentryBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function sys_get_temp_dir;

/**
 * Kernel of the 1.13 port tests: Framework, Sentry and this bundle; the nowo_sentry config and host services are
 * injected per test, with its own cache directory.
 */
final class PortsTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $config nowo_sentry configuration
     * @param Closure(ContainerConfigurator): void $services host services
     */
    public function __construct(
        private readonly string $id,
        private readonly array $config,
        private readonly Closure $services,
        bool $debug = true,
    ) {
        parent::__construct('test', $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SentryBundle();
        yield new NowoSentryBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::baseDir() . '/' . $this->id . '/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir() . '/' . $this->id . '/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir() . '/nowo-sentry-ports-tests';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'ports-kernel-secret', 'http_method_override' => false]);
        $container->extension('nowo_sentry', $this->config);
        ($this->services)($container);
    }
}

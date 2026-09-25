<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL;

use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use WeakMap;

use function sprintf;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ReportedSqlExceptionRegistryTest extends TestCase
{
    public function testResetClearsReportedExceptions(): void
    {
        $exception = new RuntimeException('sql');
        $registry  = new ReportedSqlExceptionRegistry();
        $registry->markReported($exception);

        $this->assertTrue($registry->isReported($exception));

        $registry->reset();

        $this->assertFalse($registry->isReported($exception));
    }

    public function testWrappedExceptionIsDetectedThroughPreviousChain(): void
    {
        $sqlException = new RuntimeException('sql');
        $registry     = new ReportedSqlExceptionRegistry();
        $registry->markReported($sqlException);

        $this->assertTrue($registry->isReported(new RuntimeException('twig wrapper', 0, $sqlException)));
        $this->assertFalse($registry->isReported(new RuntimeException('unrelated')));
    }

    /**
     * Long-running worker without kernel.reset: an exception of request 1 must neither be kept in
     * memory nor make a new exception of request 2 (possibly with a recycled object id) look reported.
     */
    public function testConsecutiveRequestsWithoutResetDoNotLeakOrCollide(): void
    {
        $registry = new ReportedSqlExceptionRegistry();

        $first = new RuntimeException('request 1');
        $registry->markReported($first);
        $firstId = spl_object_id($first);
        unset($first);

        $this->assertCount(0, $this->reported($registry));

        $second = new RuntimeException('request 2');
        $this->assertFalse($registry->isReported($second), sprintf('Object id %d reused: %s', $firstId, spl_object_id($second) === $firstId ? 'yes' : 'no'));

        $registry->markReported($second);
        $this->assertTrue($registry->isReported($second));
        $this->assertCount(1, $this->reported($registry));
    }

    /**
     * @return WeakMap<object, true>
     */
    private function reported(ReportedSqlExceptionRegistry $registry): WeakMap
    {
        $map = (new ReflectionProperty(ReportedSqlExceptionRegistry::class, 'reported'))->getValue($registry);
        $this->assertInstanceOf(WeakMap::class, $map);

        return $map;
    }
}

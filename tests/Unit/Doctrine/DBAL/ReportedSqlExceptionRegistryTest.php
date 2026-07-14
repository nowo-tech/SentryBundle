<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Doctrine\DBAL;

use Nowo\SentryBundle\Doctrine\DBAL\ReportedSqlExceptionRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
}

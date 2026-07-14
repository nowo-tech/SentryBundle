<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Sentry\AccessDeniedExceptionHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AccessDeniedExceptionHelperTest extends TestCase
{
    public function testDetectsAccessDeniedTypes(): void
    {
        $this->assertTrue(AccessDeniedExceptionHelper::isAccessDenied(new AccessDeniedException('Denied')));
        $this->assertTrue(AccessDeniedExceptionHelper::isAccessDenied(new AccessDeniedHttpException('Denied')));
        $this->assertFalse(AccessDeniedExceptionHelper::isAccessDenied(new RuntimeException('Other')));
    }

    public function testFindsAccessDeniedInPreviousChain(): void
    {
        $exception = new RuntimeException('Wrapper', 0, new AccessDeniedException('Denied'));

        $this->assertTrue(AccessDeniedExceptionHelper::hasAccessDeniedInChain($exception));
    }

    public function testReturnsFalseWhenChainHasNoAccessDenied(): void
    {
        $exception = new RuntimeException('Other', 0, new RuntimeException('Inner'));

        $this->assertFalse(AccessDeniedExceptionHelper::hasAccessDeniedInChain($exception));
    }
}

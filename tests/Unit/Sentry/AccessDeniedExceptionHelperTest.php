<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Sentry\AccessDeniedExceptionHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Test case for AccessDeniedExceptionHelper.
 */
class AccessDeniedExceptionHelperTest extends TestCase
{
    public function testIsAccessDeniedDetectsSecurityException(): void
    {
        $this->assertTrue(AccessDeniedExceptionHelper::isAccessDenied(new AccessDeniedException('Denied')));
    }

    public function testIsAccessDeniedDetectsHttpException(): void
    {
        $this->assertTrue(AccessDeniedExceptionHelper::isAccessDenied(new AccessDeniedHttpException('Denied')));
    }

    public function testIsAccessDeniedReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse(AccessDeniedExceptionHelper::isAccessDenied(new RuntimeException('Other')));
    }

    public function testHasAccessDeniedInChainFindsNestedAccessDenied(): void
    {
        $exception = new RuntimeException('Wrapper', 0, new AccessDeniedException('Denied'));

        $this->assertTrue(AccessDeniedExceptionHelper::hasAccessDeniedInChain($exception));
    }

    public function testHasAccessDeniedInChainReturnsFalseWhenChainHasNoAccessDenied(): void
    {
        $exception = new RuntimeException('Wrapper', 0, new RuntimeException('Inner'));

        $this->assertFalse(AccessDeniedExceptionHelper::hasAccessDeniedInChain($exception));
    }
}

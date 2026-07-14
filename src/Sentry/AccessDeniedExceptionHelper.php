<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Sentry;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

/**
 * Shared helpers for access denied exception detection.
 */
final class AccessDeniedExceptionHelper
{
    public static function isAccessDenied(Throwable $exception): bool
    {
        return $exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException;
    }

    public static function hasAccessDeniedInChain(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if (self::isAccessDenied($current)) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}

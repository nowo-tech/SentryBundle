<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use RuntimeException;
use Stringable;

/**
 * An id object whose `__toString()` throws (audit MX-S-1: 1.12 let this escape from the provider).
 */
final class ThrowingStringId implements Stringable
{
    public function __toString(): string
    {
        throw new RuntimeException('id proxy not initialised');
    }
}

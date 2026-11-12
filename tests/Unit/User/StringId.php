<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Stringable;

/**
 * Test user / helper of the provider tests.
 */
final class StringId implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

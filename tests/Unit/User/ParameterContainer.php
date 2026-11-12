<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;

/**
 * Minimal parameter bag: a value that is a Throwable is thrown by get() (unresolvable env placeholder).
 */
final class ParameterContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $id): mixed
    {
        $value = $this->values[$id] ?? null;
        if ($value instanceof Throwable) {
            throw $value;
        }

        return $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->values);
    }
}

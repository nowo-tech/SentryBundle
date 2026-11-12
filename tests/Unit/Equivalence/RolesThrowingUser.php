<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Equivalence;

use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user whose `getRoles()` throws: the 1.12 provider never read the roles, so the new port must still send the user.
 */
final class RolesThrowingUser implements UserInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        throw new RuntimeException('roles not loaded');
    }

    public function eraseCredentials(): void
    {
    }
}

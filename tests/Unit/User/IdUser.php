<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test user / helper of the provider tests.
 */
final class IdUser implements UserInterface
{
    public function __construct(private readonly int|string|Stringable|null $id, private readonly string $identifier)
    {
    }

    public function getId(): int|string|Stringable|null
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test user / helper of the provider tests.
 */
final class ThrowingIdUser implements UserInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function getId(): int
    {
        throw new RuntimeException('not loaded');
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

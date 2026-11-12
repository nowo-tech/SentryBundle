<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Fixtures\Ports;

use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use Nowo\SentryBundle\User\UserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function hash;

/**
 * Both ports with different answers, to prove the listener calls the framework-free one.
 */
final readonly class DualProvider implements SentryUserContextProviderInterface, UserContextProviderInterface
{
    public function contextFor(UserRef $user): ?array
    {
        return ['id' => 'member-' . hash('crc32b', $user->identifier())];
    }

    public function getUserContext(UserInterface $user): ?array
    {
        return ['id' => 'wrong-port'];
    }
}

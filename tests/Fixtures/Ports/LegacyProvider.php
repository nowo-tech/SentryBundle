<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Fixtures\Ports;

use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function hash;

/**
 * The 1.12 host provider (deprecated port), same answer as {@see OpaqueIdProvider}.
 */
final readonly class LegacyProvider implements SentryUserContextProviderInterface
{
    public function getUserContext(UserInterface $user): ?array
    {
        return ['id' => 'member-' . hash('crc32b', $user->getUserIdentifier())];
    }
}

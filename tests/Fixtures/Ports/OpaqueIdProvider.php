<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Fixtures\Ports;

use Nowo\SentryBundle\User\UserContextProviderInterface;
use Nowo\SentryBundle\User\UserRef;

use function hash;

/**
 * A DDD host's provider on the framework-free port: an opaque domain id, never the identifier.
 */
final readonly class OpaqueIdProvider implements UserContextProviderInterface
{
    public function contextFor(UserRef $user): ?array
    {
        return ['id' => 'member-' . hash('crc32b', $user->identifier())];
    }
}

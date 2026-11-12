<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Fixtures\Ports;

use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use Nowo\SentryBundle\User\UserRef;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * A host decorator of the default provider (by its service id) that implements only the new port: both aliases then
 * resolve to it, and the deprecated alias must be dropped for lint:container.
 */
#[AsDecorator(DefaultSentryUserContextProvider::class)]
final readonly class NewPortDecorator implements UserContextProviderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private UserContextProviderInterface $inner,
    ) {
    }

    public function contextFor(UserRef $user): ?array
    {
        $context = $this->inner->contextFor($user);

        return $context === null ? null : $context + ['segment' => 'decorated'];
    }
}

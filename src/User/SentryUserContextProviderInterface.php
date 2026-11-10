<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Port: the Sentry user context sent for the authenticated user of a main request (1.12+).
 *
 * The request listener calls it when `request_listener.set_user_info` is on and a user is logged in. Return the Sentry
 * user data (`id`, optionally `username`, `email`, `ip_address`, `segment` or custom keys) or `null` to send no user.
 * Point `request_listener.user_context_provider` at your service (or alias this interface) to send, for example, an
 * opaque domain id from a DDD host. Implementations must be stateless (the listener runs on long-lived workers).
 *
 * Default: {@see DefaultSentryUserContextProvider} (stable id or hash; never the identifier unless the host opts in).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface SentryUserContextProviderInterface
{
    /**
     * @return array<string, mixed>|null Sentry user data, or null to clear the user on the scope
     */
    public function getUserContext(UserInterface $user): ?array;
}

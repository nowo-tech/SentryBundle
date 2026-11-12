<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

/**
 * Port: the Sentry user context sent for the authenticated user of a main request. Framework-free twin of the
 * deprecated {@see SentryUserContextProviderInterface} (since 1.13, REQ-ARCH-001 rule 3).
 *
 * The request listener calls it when `request_listener.set_user_info` is on and a user is logged in. Return the Sentry
 * user data (`id`, optionally `username`, `email`, `ip_address`, `segment` or custom keys) or `null` to send no user.
 * Point `request_listener.user_context_provider` at your service, or alias / decorate this interface. A service
 * implementing both interfaces is called through this one. Implementations must be stateless (long-lived workers) and
 * must never keep the {@see UserRef}.
 *
 * Default: {@see DefaultSentryUserContextProvider} (stable id or hash; never the identifier unless the host opts in).
 */
interface UserContextProviderInterface
{
    /**
     * @return array<string, mixed>|null Sentry user data, or null to clear the user on the scope
     */
    public function contextFor(UserRef $user): ?array;
}

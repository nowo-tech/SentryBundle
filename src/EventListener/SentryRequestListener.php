<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\EventListener;

use Exception;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use Nowo\SentryBundle\User\UserRefFactory;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Listener that configures Sentry scope with user and request information.
 *
 * This listener is triggered on kernel.request events and sets up Sentry context
 * with user information, session data, and environment details. It enriches error
 * reports with contextual data to aid in debugging and monitoring.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SentryRequestListener
{
    private readonly SentryUserContextProviderInterface|UserContextProviderInterface $userContextProvider;

    /**
     * Constructs the Sentry request listener.
     *
     * @param HubInterface $sentryHub The Sentry hub instance for configuring error reporting scope
     * @param array<string, mixed> $config The listener configuration
     * @param string $environment The current application environment (prod, dev, staging, etc.)
     * @param Security|null $security The security service for accessing authenticated user information
     * @param SentryUserContextProviderInterface|UserContextProviderInterface|null $userContextProvider User data sent for
     *                                                                                                  the logged-in user (1.12+; the framework-free port
     *                                                                                                  since 1.13, called first when the service implements
     *                                                                                                  both); null = {@see DefaultSentryUserContextProvider}
     */
    public function __construct(
        private readonly ?HubInterface $sentryHub,
        private readonly array $config,
        private readonly string $environment,
        private readonly ?Security $security = null,
        SentryUserContextProviderInterface|UserContextProviderInterface|null $userContextProvider = null,
    ) {
        $this->userContextProvider = $userContextProvider ?? new DefaultSentryUserContextProvider($sentryHub);
    }

    /**
     * Configures Sentry scope with request and user information.
     *
     * This method:
     * 1. Sets domain and environment tags
     * 2. Configures user information if available
     * 3. Adds session ID to extra data if session exists
     *
     * @param RequestEvent $event The request event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Check if listener is enabled
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host    = $request->getHost();

        try {
            $session = $request->hasSession() ? $request->getSession() : null;
        } catch (Exception) {
            $session = null;
        }

        // 1.12+: what is sent comes from the user context provider (by default a stable id or a hash, never the
        // identifier, often an e-mail, unless the host opts in).
        $userContext = null;

        if ($this->security instanceof Security && ($this->config['set_user_info'] ?? true)) {
            $user = $this->security->getUser();

            if ($user instanceof UserInterface) {
                try {
                    $userContext = $this->userContextProvider instanceof UserContextProviderInterface
                        ? $this->userContextProvider->contextFor(UserRefFactory::fromUser($user))
                        : $this->userContextProvider->getUserContext($user);
                } catch (Throwable) {
                    $userContext = null;
                }
            }
        }

        try {
            // Verify Sentry is properly configured and hub is available
            if (!$this->sentryHub instanceof HubInterface || !interface_exists(HubInterface::class)) {
                return;
            }

            $this->sentryHub->configureScope(
                // @igor-ignore - Not shared worker service state.
                callback: function (Scope $scope) use ($host, $userContext, $session): void {
                    try {
                        if ($this->config['set_domain_tag'] ?? true) {
                            $scope->setTag(key: 'domain', value: $host);
                        }

                        if ($this->config['set_environment_tag'] ?? true) {
                            $scope->setTag(key: 'environment', value: $this->environment);
                        }

                        // The hub scope outlives the request in worker mode (FrankenPHP, RoadRunner): clear what a
                        // previous request set, or errors of an anonymous request are attributed to the previous user.
                        if ($this->config['set_user_info'] ?? true) {
                            // Scope::setUser() MERGES into the existing user: clear first, or metadata of the previous
                            // request's user (custom keys, sentry-symfony's impersonator_username) stays on this one.
                            $scope->removeUser();
                            if ($userContext !== null && $userContext !== []) {
                                $scope->setUser(user: $userContext);
                            }
                        }

                        if ($this->config['set_session_id'] ?? false) {
                            $scope->setExtra(
                                key: 'session_id',
                                value: $session instanceof SessionInterface && $session->isStarted() ? $session->getId() : null,
                            );
                        }
                    } catch (Throwable $e) {
                        // Silently ignore Sentry configuration errors to prevent breaking the application
                        // This handles cases where Sentry credentials are invalid or package is not properly installed
                    }
                },
            );
        } catch (Throwable $e) {
            // Silently ignore Sentry errors to prevent breaking the application
            // This handles cases where Sentry is not configured, credentials are invalid,
            // or the sentry/sentry-symfony package is not installed
        }
    }
}

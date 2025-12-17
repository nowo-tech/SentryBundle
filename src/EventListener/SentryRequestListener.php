<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\EventListener;

use Exception;
use Redis\Exception\RedisException;
use RuntimeException;
use Sentry\State\{HubInterface, Scope};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Listener that configures Sentry scope with user and request information
 *
 * This listener is triggered on kernel.request events and sets up Sentry context
 * with user information, session data, and environment details. It enriches error
 * reports with contextual data to aid in debugging and monitoring.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final readonly class SentryRequestListener
{
    /**
     * Constructs the Sentry request listener
     *
     * @param HubInterface         $sentryHub   The Sentry hub instance for configuring error reporting scope
     * @param array<string, mixed> $config      The listener configuration
     * @param string               $environment The current application environment (prod, dev, staging, etc.)
     * @param Security|null        $security    The security service for accessing authenticated user information
     */
    public function __construct(
        private HubInterface $sentryHub,
        private array $config,
        private string $environment,
        private ?Security $security = null
    ) {
    }

    /**
     * Configures Sentry scope with request and user information
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
        $host = $request->getHost();

        try {
            $session = $request->hasSession() ? $request->getSession() : null;
        } catch (RedisException|RuntimeException|Exception) {
            $session = null;
        }

        $user = null;
        $userIdentifier = null;

        if ($this->security instanceof Security && ($this->config['set_user_info'] ?? true)) {
            $user = $this->security->getUser();

            if ($user instanceof UserInterface) {
                $userIdentifier = method_exists($user, 'getUserIdentifier')
                    ? $user->getUserIdentifier()
                    : (string) $user;
            }
        }

        $this->sentryHub->configureScope(
            callback: function (Scope $scope) use ($host, $userIdentifier, $session, $user): void {
                if ($this->config['set_domain_tag'] ?? true) {
                    $scope->setTag(key: 'domain', value: $host);
                }

                if ($this->config['set_environment_tag'] ?? true) {
                    $scope->setTag(key: 'environment', value: $this->environment);
                }

                if ($userIdentifier && ($this->config['set_user_info'] ?? true)) {
                    $scope->setUser(user: [
                        'id' => $userIdentifier ?? 'anonymous',
                        'username' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
                    ]);
                }

                if ($session instanceof SessionInterface && $session->isStarted() && ($this->config['set_session_id'] ?? true)) {
                    $scope->setExtra(key: 'session_id', value: $session->getId());
                }
            }
        );
    }
}

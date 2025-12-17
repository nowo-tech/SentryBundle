<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\EventListener;

use Sentry\State\HubInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Listener that prevents Sentry from reporting AccessDeniedException errors
 *
 * This listener intercepts AccessDeniedException events before they reach Sentry
 * and marks them as handled to prevent them from being reported. This is useful
 * for reducing noise in Sentry by filtering out expected access denied errors
 * that don't require investigation.
 *
 * The listener runs with priority 255 to ensure it executes before the security
 * check listener.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final readonly class IgnoreAccessDeniedSentryListener
{
    /**
     * Constructs the ignore access denied Sentry listener
     *
     * @param HubInterface         $sentryHub The Sentry hub instance for configuring error reporting
     * @param array<string, mixed> $config    The listener configuration
     */
    public function __construct(
        private HubInterface $sentryHub,
        private array $config
    ) {
    }

    /**
     * Filters out AccessDeniedException from Sentry reporting
     *
     * This method is invoked when an exception event occurs. If the exception
     * is an AccessDeniedException, it prevents Sentry from reporting it by
     * setting a callback that returns null, effectively discarding the event.
     *
     * @param ExceptionEvent $event The exception event containing the thrown exception
     */
    public function __invoke(ExceptionEvent $event): void
    {
        // Check if listener is enabled
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException) {
            // Marcar como "handled", para que SentryBundle no lo reporte
            $event->allowCustomResponseCode();
            $this->sentryHub->getClient()?->getOptions()->setBeforeSendCallback(fn (): null => null);
        }
    }
}

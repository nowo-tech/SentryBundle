<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Sentry;

use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Filters Sentry events before they are sent.
 *
 * Pure access denied responses (main or sub-request) are dropped. Failures where a
 * sub-request access denied breaks the parent page (e.g. Twig RuntimeError wrapping
 * AccessDeniedException) are kept because the reported exception is not access denied itself.
 *
 * Wire as `sentry.options.before_send` (the bundle can register it automatically via
 * {@see \Nowo\SentryBundle\DependencyInjection\NowoSentryExtension::prepend}).
 */
final class BeforeSendHandler
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config
    ) {
    }

    public function __invoke(?Event $event, ?EventHint $hint): ?Event
    {
        if (!$event instanceof Event || !($this->config['enabled'] ?? true)) {
            return $event;
        }

        if (!($this->config['ignore_pure_access_denied'] ?? true)) {
            return $event;
        }

        $exception = $hint?->exception;

        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            return null;
        }

        return $event;
    }
}

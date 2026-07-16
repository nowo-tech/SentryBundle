<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Sentry;

use Sentry\Event;
use Sentry\EventHint;

use function array_slice;
use function count;
use function is_array;

/**
 * Trims oversized Sentry transactions so Relay does not reject them with
 * "envelope exceeded size limits for type 'event'" (~1 MiB per event item).
 *
 * Heavy Symfony pages (many Twig modal sub-requests + DBAL/Twig/cache spans)
 * can produce transactions that exceed the ingest limit and are dropped.
 *
 * Wire as `sentry.options.before_send_transaction` (the bundle can register it
 * automatically via {@see \Nowo\SentryBundle\DependencyInjection\NowoSentryExtension::prepend}).
 */
final class BeforeSendTransactionHandler
{
    private readonly EventPayloadTrimmer $trimmer;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        ?EventPayloadTrimmer $trimmer = null,
    ) {
        $this->trimmer = $trimmer ?? new EventPayloadTrimmer($config);
    }

    public function __invoke(?Event $event, ?EventHint $hint = null): ?Event
    {
        if (!$event instanceof Event || !($this->config['enabled'] ?? true)) {
            return $event;
        }

        $this->truncateSpans($event);
        $this->truncateBreadcrumbs($event);
        $this->truncateRequest($event);
        $this->truncateExtra($event);
        $this->truncateContexts($event);

        return $event;
    }

    private function truncateSpans(Event $event): void
    {
        $maxSpans = (int) ($this->config['max_spans'] ?? 400);
        if ($maxSpans <= 0) {
            return;
        }

        $spans = $event->getSpans();
        $count = count($spans);
        if ($count <= $maxSpans) {
            return;
        }

        $event->setSpans(array_slice($spans, 0, $maxSpans));
        $event->setExtra(array_merge($event->getExtra(), [
            'spans_truncated'      => true,
            'spans_original_count' => $count,
            'spans_kept'           => $maxSpans,
        ]));
    }

    private function truncateBreadcrumbs(Event $event): void
    {
        $maxBreadcrumbs = (int) ($this->config['max_breadcrumbs'] ?? 50);
        if ($maxBreadcrumbs <= 0) {
            return;
        }

        $breadcrumbs = $event->getBreadcrumbs();
        if (count($breadcrumbs) <= $maxBreadcrumbs) {
            return;
        }

        $event->setBreadcrumb(array_slice($breadcrumbs, -$maxBreadcrumbs));
    }

    private function truncateRequest(Event $event): void
    {
        $request = $event->getRequest();
        if ($request === []) {
            return;
        }

        $event->setRequest($this->trimmer->truncateRequest($request));
    }

    private function truncateExtra(Event $event): void
    {
        $extra = $event->getExtra();
        if ($extra === []) {
            return;
        }

        $event->setExtra($this->trimmer->truncateArray($extra));
    }

    private function truncateContexts(Event $event): void
    {
        foreach ($event->getContexts() as $name => $context) {
            if (!is_array($context) || $name === 'trace') {
                continue;
            }

            $event->setContext($name, $this->trimmer->truncateArray($context));
        }
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Service;

use Nowo\SentryBundle\Service\SentryErrorReporter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\State\Hub;

/**
 * Long-running worker without any scope/kernel reset: per-call context passed to captureException()
 * / captureMessage() must only be attached to that event, never to later events (other users).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SentryErrorReporterWorkerModeTest extends TestCase
{
    /** @var list<Event> */
    private array $events = [];

    private Hub $hub;

    protected function setUp(): void
    {
        $this->events = [];
        $client       = ClientBuilder::create([
            'default_integrations' => false,
            'before_send'          => function (Event $event): ?Event {
                $this->events[] = $event;

                return null;
            },
        ])->getClient();
        $this->hub = new Hub($client);
    }

    public function testPerCallContextOfCaptureExceptionDoesNotLeakIntoNextRequest(): void
    {
        $reporter = new SentryErrorReporter($this->hub);

        // Request 1 (user A): caught SQL error with its SQL literals.
        $reporter->captureException(new RuntimeException('duplicate key'), [
            'sql'        => "INSERT INTO users (email) VALUES ('a@example.com')",
            'connection' => 'default',
        ], 'custom for A');

        // Request 2 (user B) on the same worker, nothing reset in between.
        $reporter->captureException(new RuntimeException('other failure'));
        $reporter->captureMessage('plain message');

        $this->assertCount(3, $this->events);
        $this->assertSame('default', $this->events[0]->getExtra()['connection'] ?? null);
        $this->assertSame('custom for A', $this->events[0]->getExtra()['custom_message'] ?? null);
        $this->assertArrayHasKey('sql', $this->events[0]->getExtra());

        foreach ([$this->events[1], $this->events[2]] as $event) {
            $this->assertArrayNotHasKey('sql', $event->getExtra());
            $this->assertArrayNotHasKey('connection', $event->getExtra());
            $this->assertArrayNotHasKey('custom_message', $event->getExtra());
        }
    }

    public function testPerCallContextOfCaptureMessageDoesNotLeakIntoNextRequest(): void
    {
        $reporter = new SentryErrorReporter($this->hub);

        $reporter->captureMessage('checkout failed', 'warning', ['order_id' => 'A-1']);
        $reporter->captureMessage('another request');

        $this->assertCount(2, $this->events);
        $this->assertSame('A-1', $this->events[0]->getExtra()['order_id'] ?? null);
        $this->assertArrayNotHasKey('order_id', $this->events[1]->getExtra());
    }

    public function testExplicitSetContextStillAppliesToLaterEventsOfTheScope(): void
    {
        $reporter = new SentryErrorReporter($this->hub);

        $reporter->setContext(['tenant' => 't1']);
        $reporter->captureMessage('with context', 'error', ['order_id' => 'A-1']);

        $this->assertSame('t1', $this->events[0]->getExtra()['tenant'] ?? null);
        $this->assertSame('A-1', $this->events[0]->getExtra()['order_id'] ?? null);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Sentry\BeforeSendChain;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;

final class BeforeSendChainTest extends TestCase
{
    public function testPrimaryNullShortCircuitsWithoutCallingNext(): void
    {
        $nextCalled = false;
        $primary    = static fn (?Event $event, ?EventHint $hint): ?Event => null;
        $next       = static function (?Event $event, ?EventHint $hint) use (&$nextCalled): ?Event {
            $nextCalled = true;

            return $event;
        };

        $chain = new BeforeSendChain($primary, $next);
        $this->assertNull($chain(Event::createEvent(), null));
        $this->assertFalse($nextCalled);
    }

    public function testChainsToNextWhenPrimaryKeepsEvent(): void
    {
        $event   = Event::createEvent();
        $primary = static fn (?Event $e, ?EventHint $hint): ?Event => $e;
        $next    = static fn (?Event $e, ?EventHint $hint): ?Event => null;

        $chain = new BeforeSendChain($primary, $next);
        $this->assertNull($chain($event, null));
    }

    public function testReturnsPrimaryResultWhenNextIsNull(): void
    {
        $event   = Event::createEvent();
        $primary = static fn (?Event $e, ?EventHint $hint): ?Event => $e;

        $chain = new BeforeSendChain($primary, null);
        $this->assertSame($event, $chain($event, null));
    }
}

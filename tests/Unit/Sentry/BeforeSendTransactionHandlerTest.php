<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Sentry\BeforeSendTransactionHandler;
use Nowo\SentryBundle\Sentry\EventPayloadTrimmer;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

use function strlen;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class BeforeSendTransactionHandlerTest extends TestCase
{
    public function testTruncatesSpansAboveLimit(): void
    {
        $handler = new BeforeSendTransactionHandler([
            'enabled'   => true,
            'max_spans' => 3,
        ]);

        $event = Event::createTransaction();
        $spans = [];
        for ($i = 0; $i < 10; ++$i) {
            $context = SpanContext::make()->setOp('db.query')->setDescription('SELECT ' . $i);
            $spans[] = new Span($context);
        }
        $event->setSpans($spans);

        $result = $handler($event);

        $this->assertSame($event, $result);
        $this->assertCount(3, $result->getSpans());
        $this->assertTrue($result->getExtra()['spans_truncated']);
        $this->assertSame(10, $result->getExtra()['spans_original_count']);
        $this->assertSame(3, $result->getExtra()['spans_kept']);
    }

    public function testKeepsSpansWhenUnderLimit(): void
    {
        $handler = new BeforeSendTransactionHandler([
            'enabled'   => true,
            'max_spans' => 400,
        ]);

        $event = Event::createTransaction();
        $event->setSpans([
            new Span(SpanContext::make()->setOp('http.server')),
            new Span(SpanContext::make()->setOp('db.query')),
        ]);

        $result = $handler($event);

        $this->assertInstanceOf(Event::class, $result);
        $this->assertCount(2, $result->getSpans());
        $this->assertArrayNotHasKey('spans_truncated', $result->getExtra());
    }

    public function testTruncatesBreadcrumbsKeepingNewest(): void
    {
        $handler = new BeforeSendTransactionHandler([
            'enabled'         => true,
            'max_breadcrumbs' => 2,
        ]);

        $event = Event::createTransaction();
        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'a', 'one'),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'b', 'two'),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'c', 'three'),
        ]);

        $result = $handler($event);
        $this->assertInstanceOf(Event::class, $result);
        $breadcrumbs = $result->getBreadcrumbs();

        $this->assertCount(2, $breadcrumbs);
        $this->assertSame('two', $breadcrumbs[0]->getMessage());
        $this->assertSame('three', $breadcrumbs[1]->getMessage());
    }

    public function testTruncatesLargeRequestBodyStrings(): void
    {
        $handler = new BeforeSendTransactionHandler([
            'enabled'           => true,
            'max_string_length' => 10,
        ], new EventPayloadTrimmer([
            'max_string_length' => 10,
        ]));

        $event = Event::createTransaction();
        $event->setRequest([
            'data' => str_repeat('x', 50),
        ]);

        $result = $handler($event);
        $this->assertInstanceOf(Event::class, $result);
        $data = $result->getRequest()['data'];

        $this->assertIsString($data);
        $this->assertStringEndsWith('…[truncated]', $data);
        $this->assertLessThan(50, strlen($data));
    }

    public function testReturnsUnmodifiedWhenDisabled(): void
    {
        $handler = new BeforeSendTransactionHandler(['enabled' => false, 'max_spans' => 1]);
        $event   = Event::createTransaction();
        $event->setSpans([
            new Span(SpanContext::make()->setOp('a')),
            new Span(SpanContext::make()->setOp('b')),
        ]);

        $result = $handler($event);

        $this->assertSame($event, $result);
        $this->assertCount(2, $result->getSpans());
    }

    public function testReturnsNullEventAsIs(): void
    {
        $handler = new BeforeSendTransactionHandler(['enabled' => true]);

        $this->assertNull($handler(null));
    }
}

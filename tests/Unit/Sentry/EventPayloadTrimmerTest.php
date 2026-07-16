<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Sentry;

use Nowo\SentryBundle\Sentry\EventPayloadTrimmer;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class EventPayloadTrimmerTest extends TestCase
{
    public function testTruncateStringRespectsMaxLength(): void
    {
        $trimmer = new EventPayloadTrimmer(['max_string_length' => 5]);

        $this->assertSame('hello', $trimmer->truncateString('hello'));
        $this->assertSame('hello…[truncated]', $trimmer->truncateString('hello world'));
    }

    public function testTruncateArrayLimitsKeysAndDepth(): void
    {
        $trimmer = new EventPayloadTrimmer([
            'max_array_keys'    => 2,
            'max_array_depth'   => 1,
            'max_string_length' => 100,
        ]);

        $result = $trimmer->truncateArray([
            'a' => '1',
            'b' => ['nested' => ['deep' => 'value']],
            'c' => '3',
        ]);

        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
        $this->assertArrayHasKey('_truncated', $result);
        $this->assertSame('max keys', $result['_truncated']);
        $this->assertSame(['nested' => ['_truncated' => 'max depth']], $result['b']);
    }

    public function testTruncateRequestHandlesDataAndHeaders(): void
    {
        $trimmer = new EventPayloadTrimmer(['max_string_length' => 4]);

        $result = $trimmer->truncateRequest([
            'data'    => 'abcdefgh',
            'headers' => ['Authorization' => 'Bearer secret-token'],
            'url'     => 'https://example.test',
        ]);

        $this->assertSame('abcd…[truncated]', $result['data']);
        $this->assertSame('Bear…[truncated]', $result['headers']['Authorization']);
        $this->assertSame('https://example.test', $result['url']);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Sentry;

use function is_array;
use function is_object;
use function is_string;
use function strlen;

/**
 * Truncates large arrays/strings on Sentry payloads to stay under Relay envelope limits.
 *
 * @see https://develop.sentry.dev/sdk/envelopes/#size-limits
 * @see https://docs.sentry.io/concepts/data-management/size-limits/
 */
class EventPayloadTrimmer
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function truncateRequest(array $request): array
    {
        foreach (['data', 'env', 'headers', 'cookies', 'query_string'] as $key) {
            if (!isset($request[$key])) {
                continue;
            }

            if (is_string($request[$key])) {
                $request[$key] = $this->truncateString($request[$key]);
            } elseif (is_array($request[$key])) {
                $request[$key] = $this->truncateArray($request[$key], 1);
            }
        }

        return $request;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public function truncateArray(array $data, int $depth = 0): array
    {
        $maxDepth = (int) ($this->config['max_array_depth'] ?? 3);
        $maxKeys  = (int) ($this->config['max_array_keys'] ?? 50);

        if ($depth > $maxDepth) {
            return ['_truncated' => 'max depth'];
        }

        $result = [];
        $i      = 0;

        foreach ($data as $key => $value) {
            if ($i >= $maxKeys) {
                $result['_truncated'] = 'max keys';

                break;
            }
            ++$i;

            if (is_string($value)) {
                $result[$key] = $this->truncateString($value);
            } elseif (is_array($value)) {
                $result[$key] = $this->truncateArray($value, $depth + 1);
            } elseif (is_object($value)) {
                $result[$key] = 'object:' . $value::class;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function truncateString(string $value): string
    {
        $maxLength = (int) ($this->config['max_string_length'] ?? 2048);

        if ($maxLength <= 0 || strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . '…[truncated]';
    }
}
